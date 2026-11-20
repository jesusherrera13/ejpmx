<?php

namespace App\Http\Controllers;

use App\Models\FieldingPosition;

/**
 * Catálogo de posiciones del roster (lo llena FieldingPositionSeeder), en el orden del listado.
 */
class FieldingPositionController extends Controller
{
    public function index()
    {
        return response()->json(
            FieldingPosition::orderBy('sort_order')->get(['id', 'number', 'code', 'name', 'name_en'])
        );
    }
}
