<?php

namespace AmjadIqbal\GridJS\Http\Controllers;

use AmjadIqbal\GridJS\TokenBinding;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Serves rows for one grid. Everything that decides which rows and columns can be returned
 * comes from the encrypted token GridBuilder wrote; the request only picks a page, a search
 * keyword and one of the token's sortable fields.
 */
class GridDataController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $grid = $this->decode($request->query('grid'));

        /** @var Model $model */
        $model = new $grid['model'];
        $model->setConnection($grid['connection']);
        $table = $model->getTable();

        $query = $model->newQueryWithoutScopes();
        $wrapped = $query->getQuery()->getGrammar()->wrapTable($table);
        $query->fromRaw('('.$grid['sql'].') as '.$wrapped, $grid['bindings']);

        if ($grid['with'] !== []) {
            $query->with($grid['with']);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '' && $grid['search'] !== []) {
            $query->where(function (Builder $q) use ($grid, $search, $table) {
                foreach ($grid['search'] as $field) {
                    if (str_contains($field, '.')) {
                        $relation = Str::beforeLast($field, '.');
                        $column = Str::afterLast($field, '.');
                        $q->orWhereHas($relation, fn (Builder $r) => $r->whereLike($r->qualifyColumn($column), '%'.$search.'%'));
                    } else {
                        $q->orWhereLike($table.'.'.$field, '%'.$search.'%');
                    }
                }
            });
        }

        $sort = $request->query('sort');
        if (is_string($sort) && in_array($sort, $grid['sort'], true)) {
            $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($table.'.'.$sort, $direction);
        } else {
            $bindings = $grid['order_bindings'];
            foreach ($grid['orders'] as $order) {
                if (isset($order['column'])) {
                    $query->orderBy($table.'.'.$order['column'], $order['direction']);
                } else {
                    $count = substr_count($order['sql'], '?');
                    $query->orderByRaw($order['sql'], array_splice($bindings, 0, $count));
                }
            }
        }

        if ($grid['per_page'] !== null) {
            $page = max(1, (int) $request->query('page', 1));
            $paginator = $query->paginate((int) $grid['per_page'], ['*'], 'page', $page);
            $items = $paginator->items();
            $total = $paginator->total();
        } else {
            $items = $query->limit(max(1, (int) config('gridjs.max_rows', 1000)))->get()->all();
            $total = count($items);
        }

        $rows = [];
        foreach ($items as $item) {
            $row = [];
            foreach ($grid['fields'] as $field) {
                $row[] = $field === null ? null : $this->value(data_get($item, $field));
            }
            $rows[] = $row;
        }

        return new JsonResponse(['data' => $rows, 'total' => $total]);
    }

    /**
     * @return array{model: class-string<Model>, connection: ?string, sql: string, bindings: list<mixed>, orders: list<array<string, string>>, order_bindings: list<mixed>, with: list<string>, fields: list<?string>, search: list<string>, sort: list<string>, per_page: ?int, bind: ?string, exp: int}
     */
    protected function decode(mixed $token): array
    {
        abort_unless(is_string($token) && $token !== '', 403);

        try {
            $grid = json_decode(Crypt::decryptString($token), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            abort(403);
        }

        abort_unless(
            is_array($grid) && ($grid['v'] ?? null) === 1 && is_string($grid['model'] ?? null)
                && class_exists($grid['model']) && is_subclass_of($grid['model'], Model::class),
            403
        );

        // Expired, or rendered for another user or browser session.
        abort_unless(is_int($grid['exp'] ?? null) && $grid['exp'] >= now()->getTimestamp(), 403, 'This grid has expired. Reload the page.');
        abort_unless(TokenBinding::matches(is_string($grid['bind'] ?? null) ? $grid['bind'] : null), 403);

        return $grid;
    }

    protected function value(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $value;
    }
}
