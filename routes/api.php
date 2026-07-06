<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AulaController;
use App\Http\Controllers\Api\AlumnoController;
use App\Http\Controllers\Api\VerificacionController;
use App\Http\Controllers\Api\ReporteController;

Route::get('/aulas', [AulaController::class, 'index']);
Route::post('/aulas', [AulaController::class, 'store']);
Route::put('/aulas/{id}', [AulaController::class, 'update']);
Route::delete('/aulas/{id}', [AulaController::class, 'destroy']);

Route::get('/alumnos', [AlumnoController::class, 'index']);
Route::get('/alumnos/{id}', [AlumnoController::class, 'show']);
Route::put('/alumnos/{id}', [AlumnoController::class, 'update']);
Route::put('/alumnos/{id}/mover', [AlumnoController::class, 'mover']);
Route::delete('/alumnos/{id}', [AlumnoController::class, 'destroy']);

Route::post('/registrar-rostro', [VerificacionController::class, 'registrarRostro']);
Route::post('/verificar-rostro', [VerificacionController::class, 'verificarRostro']);
Route::post('/detectar-rostro-simple', [VerificacionController::class, 'detectarRostroSimple']);

Route::get('/reportes/logs', [ReporteController::class, 'logs']);
Route::get('/reportes/exportar', [ReporteController::class, 'exportar']);
