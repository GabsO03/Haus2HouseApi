<?php

namespace App\Http\Controllers;

use App\Models\Worker;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Notifications\ServiceAssignedNotification;

class UserController extends Controller
{
    
    /**
     * Notifica a cliente y trabajador que ya asginaron su servicio
     */
    public static function notifyUsers(Service $service, Worker $worker)
    {
        $service->client->user->notify(new ServiceAssignedNotification($service));
        $worker->user->notify(new ServiceAssignedNotification($service));
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
