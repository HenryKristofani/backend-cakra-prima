<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    #[OA\Get(
        path: "/api/projects",
        summary: "Daftar project",
        tags: ["Projects"],
        parameters: [
            new OA\Parameter(
                name: "status",
                in: "query",
                description: "Filter berdasarkan status (aktif | nonaktif)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["aktif", "nonaktif"])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Daftar project berhasil diambil",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "name", type: "string", example: "Jomboran"),
                            new OA\Property(property: "status", type: "string", example: "aktif"),
                            new OA\Property(property: "created_at", type: "string", format: "date-time"),
                            new OA\Property(property: "updated_at", type: "string", format: "date-time")
                        ]
                    )
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Project::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $projects = $query->orderBy('name')->get();

        return response()->json($projects);
    }

    #[OA\Post(
        path: "/api/projects",
        summary: "Tambah project baru",
        tags: ["Projects"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Kreasi Muda"),
                    new OA\Property(property: "status", type: "string", enum: ["aktif", "nonaktif"], example: "aktif")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Project berhasil dibuat",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "name", type: "string", example: "Kreasi Muda"),
                        new OA\Property(property: "status", type: "string", example: "aktif")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validasi gagal")
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:aktif,nonaktif',
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'aktif',
        ]);

        return response()->json($project, 201);
    }

    #[OA\Get(
        path: "/api/projects/{id}",
        summary: "Detail project",
        tags: ["Projects"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Detail project berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "name", type: "string", example: "Jomboran"),
                        new OA\Property(property: "status", type: "string", example: "aktif")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Project tidak ditemukan")
        ]
    )]
    public function show(Project $project): JsonResponse
    {
        return response()->json($project);
    }

    #[OA\Put(
        path: "/api/projects/{id}",
        summary: "Update project",
        tags: ["Projects"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Jomboran Revisi"),
                    new OA\Property(property: "status", type: "string", enum: ["aktif", "nonaktif"], example: "nonaktif")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Project berhasil diupdate",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "name", type: "string", example: "Jomboran Revisi"),
                        new OA\Property(property: "status", type: "string", example: "nonaktif")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Project tidak ditemukan"),
            new OA\Response(response: 422, description: "Validasi gagal")
        ]
    )]
    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:aktif,nonaktif',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    #[OA\Delete(
        path: "/api/projects/{id}",
        summary: "Hapus project",
        tags: ["Projects"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Project berhasil dihapus"),
            new OA\Response(response: 404, description: "Project tidak ditemukan")
        ]
    )]
    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(null, 204);
    }
}
