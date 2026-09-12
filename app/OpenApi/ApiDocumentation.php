<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'VetCare API',
    version: '1.0.0',
    description: 'Documentacion de la API REST del sistema veterinario VetCare'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Servidor local de desarrollo'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token'
)]
#[OA\Schema(
    schema: 'SpeciesSummary',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Canino'),
    ]
)]
#[OA\Schema(
    schema: 'BreedSummary',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'species_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Mestizo'),
    ]
)]
#[OA\Schema(
    schema: 'Pet',
    type: 'object',
    required: ['id', 'owner_id', 'species_id', 'breed_id', 'name', 'sex', 'is_active'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true, example: 12),
        new OA\Property(property: 'owner_id', type: 'integer', format: 'int64', readOnly: true, example: 4),
        new OA\Property(property: 'species_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'breed_id', type: 'integer', format: 'int64', example: 2),
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Luna'),
        new OA\Property(property: 'sex', type: 'string', enum: ['MACHO', 'HEMBRA'], example: 'HEMBRA'),
        new OA\Property(property: 'birth_date', type: 'string', format: 'date', nullable: true, example: '2022-06-15'),
        new OA\Property(property: 'weight', type: 'number', format: 'float', nullable: true, example: 12.5),
        new OA\Property(property: 'color', type: 'string', maxLength: 50, nullable: true, example: 'Marrón'),
        new OA\Property(property: 'observations', type: 'string', maxLength: 2000, nullable: true, example: 'Alergia estacional'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'species', ref: '#/components/schemas/SpeciesSummary', readOnly: true),
        new OA\Property(property: 'breed', ref: '#/components/schemas/BreedSummary', readOnly: true),
    ]
)]
#[OA\Schema(
    schema: 'PetRequest',
    type: 'object',
    required: ['name', 'species_id', 'breed_id', 'sex'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Luna'),
        new OA\Property(property: 'species_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'breed_id', type: 'integer', format: 'int64', example: 2),
        new OA\Property(property: 'sex', type: 'string', enum: ['MACHO', 'HEMBRA'], example: 'HEMBRA'),
        new OA\Property(property: 'birth_date', type: 'string', format: 'date', nullable: true, example: '2022-06-15'),
        new OA\Property(property: 'weight', type: 'number', format: 'float', nullable: true, example: 12.5),
        new OA\Property(property: 'color', type: 'string', maxLength: 50, nullable: true, example: 'Marrón'),
        new OA\Property(property: 'observations', type: 'string', maxLength: 2000, nullable: true, example: 'Alergia estacional'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ]
)]
#[OA\Schema(
    schema: 'PetPatchRequest',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Luna actualizada'),
        new OA\Property(property: 'species_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'breed_id', type: 'integer', format: 'int64', example: 2),
        new OA\Property(property: 'sex', type: 'string', enum: ['MACHO', 'HEMBRA'], example: 'HEMBRA'),
        new OA\Property(property: 'birth_date', type: 'string', format: 'date', nullable: true, example: '2022-06-15'),
        new OA\Property(property: 'weight', type: 'number', format: 'float', nullable: true, example: 12.5),
        new OA\Property(property: 'color', type: 'string', maxLength: 50, nullable: true, example: 'Marrón'),
        new OA\Property(property: 'observations', type: 'string', maxLength: 2000, nullable: true, example: 'Alergia estacional'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    type: 'object',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            )
        ),
    ]
)]
final class ApiDocumentation
{
    #[OA\Post(
        path: '/api/auth/token',
        operationId: 'createApiToken',
        summary: 'Generar token de acceso',
        description: 'Genera un token Sanctum para un usuario activo usando su username o email.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['login', 'password', 'device_name'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', maxLength: 255, example: 'ana.owner'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', maxLength: 255, example: 'password'),
                    new OA\Property(property: 'device_name', type: 'string', maxLength: 100, example: 'Postman'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token generado correctamente',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: '1|sanctum-token'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 4),
                                new OA\Property(property: 'username', type: 'string', example: 'ana.owner'),
                                new OA\Property(property: 'name', type: 'string', example: 'Ana Owner'),
                                new OA\Property(property: 'role', type: 'string', example: 'OWNER'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'La cuenta no tiene acceso'
            ),
            new OA\Response(
                response: 422,
                description: 'Credenciales o datos de entrada inválidos',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function createApiToken(): void {}

    #[OA\Get(
        path: '/api/owner/pets',
        operationId: 'listOwnerPets',
        summary: 'Listar mascotas del propietario',
        tags: ['Mascotas del propietario'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de mascotas activas',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Pet')
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page', type: 'integer', example: 3),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 42),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Token ausente o inválido'),
            new OA\Response(response: 403, description: 'El usuario no tiene el rol OWNER o está inactivo'),
        ]
    )]
    public function listOwnerPets(): void {}

    #[OA\Post(
        path: '/api/owner/pets',
        operationId: 'createOwnerPet',
        summary: 'Crear una mascota',
        tags: ['Mascotas del propietario'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PetRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Mascota creada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Pet'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Token ausente o inválido'),
            new OA\Response(response: 403, description: 'El usuario no tiene el rol OWNER, está inactivo o no tiene perfil de propietario'),
            new OA\Response(response: 422, description: 'Datos inválidos o raza incompatible con la especie', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function createOwnerPet(): void {}

    #[OA\Get(
        path: '/api/owner/pets/{pet}',
        operationId: 'showOwnerPet',
        summary: 'Consultar una mascota',
        tags: ['Mascotas del propietario'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'pet', in: 'path', required: true, description: 'ID de la mascota', schema: new OA\Schema(type: 'integer', format: 'int64'), example: 12),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mascota encontrada', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Pet')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o inválido'),
            new OA\Response(response: 403, description: 'El usuario no tiene el rol OWNER o está inactivo'),
            new OA\Response(response: 404, description: 'Mascota no encontrada o no pertenece al propietario'),
        ]
    )]
    public function showOwnerPet(): void {}

    #[OA\Put(
        path: '/api/owner/pets/{pet}',
        operationId: 'replaceOwnerPet',
        summary: 'Reemplazar una mascota',
        tags: ['Mascotas del propietario'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'pet', in: 'path', required: true, description: 'ID de la mascota', schema: new OA\Schema(type: 'integer', format: 'int64'), example: 12),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PetRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Mascota actualizada', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Pet')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o inválido'),
            new OA\Response(response: 403, description: 'El usuario no tiene el rol OWNER o está inactivo'),
            new OA\Response(response: 404, description: 'Mascota no encontrada o no pertenece al propietario'),
            new OA\Response(response: 422, description: 'Datos inválidos o raza incompatible con la especie', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function replaceOwnerPet(): void {}

    #[OA\Patch(
        path: '/api/owner/pets/{pet}',
        operationId: 'updateOwnerPet',
        summary: 'Actualizar parcialmente una mascota',
        tags: ['Mascotas del propietario'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'pet', in: 'path', required: true, description: 'ID de la mascota', schema: new OA\Schema(type: 'integer', format: 'int64'), example: 12),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PetPatchRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Mascota actualizada', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Pet')], type: 'object')),
            new OA\Response(response: 401, description: 'Token ausente o inválido'),
            new OA\Response(response: 403, description: 'El usuario no tiene el rol OWNER o está inactivo'),
            new OA\Response(response: 404, description: 'Mascota no encontrada o no pertenece al propietario'),
            new OA\Response(response: 422, description: 'Datos inválidos o raza incompatible con la especie', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function updateOwnerPet(): void {}

    #[OA\Delete(
        path: '/api/owner/pets/{pet}',
        operationId: 'deleteOwnerPet',
        summary: 'Eliminar una mascota',
        tags: ['Mascotas del propietario'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'pet', in: 'path', required: true, description: 'ID de la mascota', schema: new OA\Schema(type: 'integer', format: 'int64'), example: 12),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mascota eliminada correctamente',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Mascota eliminada correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Token ausente o inválido'),
            new OA\Response(response: 403, description: 'El usuario no tiene el rol OWNER o está inactivo'),
            new OA\Response(response: 404, description: 'Mascota no encontrada o no pertenece al propietario'),
            new OA\Response(response: 409, description: 'La mascota tiene citas o consultas relacionadas'),
        ]
    )]
    public function deleteOwnerPet(): void {}

    #[OA\Get(
        path: '/api/swagger-test',
        operationId: 'swaggerTest',
        summary: 'Comprobar Swagger',
        description: 'Comprueba que Laravel y Swagger estan funcionando correctamente.',
        tags: ['Pruebas'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Swagger funciona correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'success',
                            type: 'boolean',
                            example: true
                        ),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Swagger funciona correctamente'
                        ),
                        new OA\Property(
                            property: 'proyecto',
                            type: 'string',
                            example: 'VetCare API'
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function swaggerTest(): void {}
}
