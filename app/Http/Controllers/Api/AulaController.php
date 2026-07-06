<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aula;
use Illuminate\Http\Request;

class AulaController extends Controller
{
    public function index()
    {
        return response()->json(Aula::orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate(['nombre' => 'required|string']);
        $aula = Aula::create($data);
        return response()->json($aula, 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate(['nombre' => 'required|string']);
        $aula = Aula::findOrFail($id);
        $aula->update($data);
        return response()->json($aula);
    }

    public function destroy($id)
    {
        Aula::findOrFail($id)->delete();
        return response()->json(['mensaje' => 'Aula eliminada']);
    }
}
