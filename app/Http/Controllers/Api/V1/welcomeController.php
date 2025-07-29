<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class welcomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(['Utilisateurs' => [
            [
                'name' => 'Jorel KUE',
                'email' => 'jorel@example.com',
                'age' => 30,
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'Anytown',
                    'state' => 'CA',
                    'zip' => '12345'
                ]
            ],
            [
                'name' => 'Cédrick FEZE',
                'email' => 'cedrick@example.com',
                'age' => 25,
                'address' => [
                    'street' => '456 Elm St',
                    'city' => 'Othertown',
                    'state' => 'NY',
                    'zip' => '67890'
                ]
            ],
            [
                'name' => 'Stéphane KAMGA',
                'email' => 'stephane@example.com',
                'age' => 40,
                'address' => [
                    'street' => '789 Oak St',
                    'city' => 'Somewhere',
                    'state' => 'TX',
                    'zip' => '54321'
                ]
            ]
        ]]);
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
