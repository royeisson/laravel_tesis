<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GuiaController extends Controller
{
    public function index()
    {
        $guias = Guia::all()->map(function ($g) {
            return [
                'id' => $g->id,
                'nombre' => $g->nombre,
                'usuario' => $g->usuario,
            ];
        });
        return response()->json($guias);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string',
            'usuario' => 'required|string|unique:guias,usuario',
            'password' => 'required|string|min:6',
        ]);

        $guia = Guia::create([
            'nombre' => $data['nombre'],
            'usuario' => $data['usuario'],
            'password' => Hash::make($data['password']),
        ]);

        return response()->json(['id' => $guia->id, 'mensaje' => 'Guia creado']);
    }

    public function update(Request $request, $id)
    {
        $guia = Guia::findOrFail($id);
        $data = $request->validate([
            'nombre' => 'sometimes|string',
            'password' => 'sometimes|string|min:6',
        ]);

        if (isset($data['nombre'])) $guia->nombre = $data['nombre'];
        if (isset($data['password'])) $guia->password = Hash::make($data['password']);
        $guia->save();

        return response()->json(['mensaje' => 'Guia actualizado']);
    }

    public function destroy($id)
    {
        $guia = Guia::findOrFail($id);
        $guia->delete();
        return response()->json(['mensaje' => 'Guia eliminado']);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'usuario' => 'required|string',
            'password' => 'required|string',
        ]);

        $guia = Guia::where('usuario', $data['usuario'])->first();
        if (!$guia || !Hash::check($data['password'], $guia->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        return response()->json([
            'nombre' => $guia->nombre,
            'usuario' => $guia->usuario,
            'rol' => 'guia',
        ]);
    }
}