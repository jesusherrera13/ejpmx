<?php

namespace App\Http\Controllers;

use App\Models\ContentType;
use Illuminate\Http\Request;

class ContentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ContentType::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ContentType $contentType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ContentType $contentType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ContentType $contentType)
    {
        //
    }
}
