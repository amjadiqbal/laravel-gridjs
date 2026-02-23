<?php

namespace AmjadIqbal\GridJS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Response;

class GridDataController extends Controller
{
    public function index(Request $request)
    {
        $modelClass = $request->query('model');
        $columnsParam = $request->query('columns', '');
        $columns = array_values(array_filter(explode(',', $columnsParam)));
        $limit = (int) $request->query('limit', (int) $request->query('pageSize', 10));
        $page = (int) $request->query('page', 1);
        $search = $request->query('search', null);
        $sort = $request->query('sort', null);
        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (!class_exists($modelClass)) {
            return Response::json(['data' => [], 'total' => 0]);
        }

        $model = new $modelClass();
        if (!$model instanceof Model) {
            return Response::json(['data' => [], 'total' => 0]);
        }

        $query = $model->newQuery();

        if ($search && !empty($columns)) {
            $query->where(function ($q) use ($columns, $search) {
                foreach ($columns as $idx => $col) {
                    if ($idx === 0) {
                        $q->where($col, 'like', '%' . $search . '%');
                    } else {
                        $q->orWhere($col, 'like', '%' . $search . '%');
                    }
                }
            });
        }

        if ($sort && in_array($sort, $columns, true)) {
            $query->orderBy($sort, $direction);
        }

        $paginator = $query->paginate($limit, ['*'], 'page', $page);
        $items = [];
        foreach ($paginator->items() as $item) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = data_get($item, $col);
            }
            $items[] = $row;
        }

        return Response::json([
            'data' => $items,
            'total' => $paginator->total(),
        ]);
    }
}
