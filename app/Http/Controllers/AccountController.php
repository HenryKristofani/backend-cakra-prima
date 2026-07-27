<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AccountController extends Controller
{
    #[OA\Get(
        path: "/api/accounts",
        summary: "Daftar Akun Kas",
        tags: ["Accounts"],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function index()
    {
        $accounts = Account::orderBy('name')->get();
        // Append current_balance
        $accounts->each->setAppends(['current_balance']);
        return response()->json($accounts);
    }

    #[OA\Post(
        path: "/api/accounts",
        summary: "Tambah Akun Kas",
        tags: ["Accounts"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "type"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "BCA"),
                    new OA\Property(property: "type", type: "string", example: "rek"),
                    new OA\Property(property: "initial_balance", type: "number", example: 1000000)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Berhasil dibuat")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'initial_balance' => 'nullable|numeric'
        ]);

        $account = Account::create($validated);
        $account->setAppends(['current_balance']);
        return response()->json($account, 201);
    }

    #[OA\Get(
        path: "/api/accounts/{id}",
        summary: "Detail Akun Kas",
        tags: ["Accounts"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Berhasil")
        ]
    )]
    public function show(Account $account)
    {
        $account->setAppends(['current_balance']);
        return response()->json($account);
    }

    #[OA\Put(
        path: "/api/accounts/{id}",
        summary: "Update Akun Kas",
        tags: ["Accounts"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "type", type: "string"),
                    new OA\Property(property: "initial_balance", type: "number")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Berhasil diupdate")
        ]
    )]
    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:255',
            'initial_balance' => 'nullable|numeric'
        ]);

        $account->update($validated);
        $account->setAppends(['current_balance']);
        return response()->json($account);
    }

    #[OA\Delete(
        path: "/api/accounts/{id}",
        summary: "Hapus Akun Kas",
        tags: ["Accounts"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Berhasil dihapus")
        ]
    )]
    public function destroy(Account $account)
    {
        $account->delete();
        return response()->noContent();
    }
}
