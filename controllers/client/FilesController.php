<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\pterodactylpanelapi\controllers\client;

use App\App;
use App\Chat\Node;
use App\SubuserPermissions;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Config\ConfigInterface;
use App\Services\Wings\Services\JwtService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Wings\Exceptions\WingsRequestException;
use App\Services\Wings\Exceptions\WingsConnectionException;
use App\Services\Wings\Exceptions\WingsAuthenticationException;

#[OA\Tag(name: 'Plugin - Pterodactyl API - Client Files', description: 'Client-facing file management endpoints for the Pterodactyl compatibility API.')]
class FilesController extends ServersController
{
    #[OA\Get(
        path: '/api/client/servers/{identifier}/files/list',
        summary: 'List directory contents',
        description: 'Returns files and folders for the given directory on the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'directory',
                in: 'query',
                required: false,
                description: 'Directory path to list (defaults to `/`).',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Directory listing.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function list(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_READ);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $directory = $this->normalizeDirectory($request->query->get('directory', '/'));

        $wings = $this->createWingsClient($context['server']);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->listDirectory((string) $context['server']['uuid'], $directory);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to list files on Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error listing files: ' . $e->getMessage());

            return $this->daemonErrorResponse(500);
        }

        if (!$response->isSuccessful()) {
            return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
        }

        $entries = $response->getData();
        if (!is_array($entries)) {
            $entries = [];
        }

        $data = array_map(
            fn (mixed $entry): array => $this->formatFileObject(is_array($entry) ? $entry : []),
            $entries
        );

        return ApiResponse::sendManualResponse([
            'object' => 'list',
            'data' => $data,
        ], 200);
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/files/contents',
        summary: 'Get file contents',
        description: 'Returns the raw contents of the requested file.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'file',
                in: 'query',
                required: true,
                description: 'Absolute path to the file.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Raw file contents.', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'File not found.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function contents(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_READ_CONTENT);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $filePath = $this->normalizePath($request->query->get('file', null));
        if ($filePath === null) {
            return $this->validationError('file', 'The file parameter is required.', 'required');
        }

        $wings = $this->createWingsClient($context['server']);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->getFileContentsRaw((string) $context['server']['uuid'], $filePath, false);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to fetch file contents from Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error fetching file contents: ' . $e->getMessage());

            return $this->daemonErrorResponse(500);
        }

        if (!$response->isSuccessful()) {
            return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
        }

        $content = (string) $response->getRawBody();

        return new Response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/files/download',
        summary: 'Generate file download URL',
        description: 'Generates a signed download URL for the requested file.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'file',
                in: 'query',
                required: true,
                description: 'Absolute path to the file.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Signed download URL.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'File not found.'),
            new OA\Response(response: 500, description: 'Failed to generate download URL.'),
        ]
    )]
    public function download(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_READ_CONTENT);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $filePath = $this->normalizePath($request->query->get('file', null));
        if ($filePath === null) {
            return $this->validationError('file', 'The file parameter is required.', 'required');
        }

        $node = Node::getNodeById((int) ($context['server']['node_id'] ?? 0));
        if (!$node) {
            return $this->daemonErrorResponse(500, 'Node configuration is missing for this server.');
        }

        $baseUrl = $this->buildWingsBaseUrl($node);

        try {
            $jwtService = $this->createJwtService($node);
            $url = $jwtService->getTokenGenerator()->generateFileDownloadUrl(
                $baseUrl,
                (string) $context['server']['uuid'],
                $filePath
            );
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Failed to generate file download URL: ' . $e->getMessage());

            return $this->daemonErrorResponse(500, 'Failed to generate download URL.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:file.download',
            [
                'file' => $filePath,
            ]
        );

        return ApiResponse::sendManualResponse([
            'object' => 'signed_url',
            'attributes' => [
                'url' => $url,
            ],
        ], 200);
    }

    #[OA\Put(
        path: '/api/client/servers/{identifier}/files/rename',
        summary: 'Rename files',
        description: 'Renames files or folders within the specified server directory.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['root', 'files'],
                properties: [
                    new OA\Property(property: 'root', type: 'string'),
                    new OA\Property(
                        property: 'files',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'from', type: 'string'),
                                new OA\Property(property: 'to', type: 'string'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Files renamed.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function rename(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        $root = $this->normalizeDirectory($payload['root'] ?? '/');

        if (!isset($payload['files']) || !is_array($payload['files'])) {
            return $this->validationError('files', 'The files field is required.', 'required');
        }

        $operations = [];
        foreach ($payload['files'] as $index => $entry) {
            if (!is_array($entry)) {
                return $this->validationError('files.' . $index, 'Each file entry must be an object.', 'array');
            }

            $from = $this->normalizeRelativePath($entry['from'] ?? null);
            $to = $this->normalizeRelativePath($entry['to'] ?? null);

            if ($from === null) {
                return $this->validationError('files.' . $index . '.from', 'The from field is required.', 'required');
            }

            if ($to === null) {
                return $this->validationError('files.' . $index . '.to', 'The to field is required.', 'required');
            }

            $operations[] = [
                'from' => $from,
                'to' => $to,
            ];
        }

        if (empty($operations)) {
            return $this->validationError('files', 'At least one file must be provided.', 'min:1');
        }

        $wings = $this->createWingsClient($context['server']);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->renameFiles((string) $context['server']['uuid'], $root, $operations);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to rename files on Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error renaming files: ' . $e->getMessage());

            return $this->daemonErrorResponse(500);
        }

        if (!$response->isSuccessful()) {
            return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:file.rename',
            [
                'root' => $root,
                'operations' => $operations,
            ]
        );

        return new Response('', 204);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/files/copy',
        summary: 'Copy files',
        description: 'Copies files within the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['location'],
                properties: [
                    new OA\Property(property: 'location', type: 'string'),
                    new OA\Property(property: 'files', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Files copied.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function copy(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_CREATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        if (!isset($payload['location']) || !is_string($payload['location'])) {
            return $this->validationError('location', 'The location field is required.', 'required');
        }

        $rawLocation = trim($payload['location']);
        $location = $this->normalizeDirectory($rawLocation === '' ? '/' : $rawLocation);

        $files = [];
        if (isset($payload['files']) && is_array($payload['files'])) {
            $files = $this->normalizeStringList($payload['files']);
        }

        if (empty($files)) {
            $fallback = $this->deriveFilesFromLocation($rawLocation);
            if ($fallback !== null) {
                [$location, $files] = $fallback;
            }
        }

        if (empty($files)) {
            return $this->validationError('files', 'The files field is required.', 'required');
        }

        $wings = $this->createWingsClient($context['server']);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->copyFiles((string) $context['server']['uuid'], $location, $files);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to copy files on Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error copying files: ' . $e->getMessage());

            return $this->daemonErrorResponse(500);
        }

        if (!$response->isSuccessful()) {
            return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:file.copy',
            [
                'location' => $location,
                'files' => $files,
            ]
        );

        return new Response('', 204);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/files/write',
        summary: 'Write file contents',
        description: 'Writes raw content to a file on the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'file',
                in: 'query',
                required: true,
                description: 'Absolute file path to write.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Raw file contents.',
            content: new OA\MediaType(mediaType: 'text/plain')
        ),
        responses: [
            new OA\Response(response: 204, description: 'File written.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function write(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_UPDATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $filePath = $this->normalizePath($request->query->get('file', null));
        if ($filePath === null) {
            return $this->validationError('file', 'The file parameter is required.', 'required');
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        if (stripos($contentType, 'application/json') !== false) {
            return $this->validationError('Content-Type', 'JSON content is not permitted for this endpoint.', 'not_json');
        }

        $content = $request->getContent();
        if ($content === null) {
            return $this->validationError('body', 'The request body is required.', 'required');
        }

        $wings = $this->createWingsClient($context['server']);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->writeFile((string) $context['server']['uuid'], $filePath, $content);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to write file on Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error writing file: ' . $e->getMessage());

            return $this->daemonErrorResponse(500);
        }

        if (!$response->isSuccessful()) {
            return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:file.write',
            [
                'file' => $filePath,
                'bytes' => strlen($content),
            ]
        );

        return new Response('', 204);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/files/compress',
        summary: 'Compress files',
        description: 'Creates an archive from the specified files.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['root', 'files'],
                properties: [
                    new OA\Property(property: 'root', type: 'string'),
                    new OA\Property(property: 'files', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'extension', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Archive created.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function compress(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_ARCHIVE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        $root = $this->normalizeDirectory($payload['root'] ?? '/');

        if (!isset($payload['files']) || !is_array($payload['files'])) {
            return $this->validationError('files', 'The files field is required.', 'required');
        }

        $files = $this->normalizeStringList($payload['files']);
        if (empty($files)) {
            return $this->validationError('files', 'At least one file must be provided.', 'min:1');
        }

        $name = isset($payload['name']) && is_string($payload['name']) ? trim($payload['name']) : '';
        $extension = isset($payload['extension']) && is_string($payload['extension'])
            ? trim($payload['extension'])
            : 'tar.gz';

        $wings = $this->createWingsClient($context['server']);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->compressFiles(
                (string) $context['server']['uuid'],
                $root,
                $files,
                $name,
                $extension
            );
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to compress files on Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error compressing files: ' . $e->getMessage());

            return $this->daemonErrorResponse(500);
        }

        if (!$response->isSuccessful()) {
            return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
        }

        $file = $response->getData();
        if (!is_array($file)) {
            $file = [];
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:file.compress',
            [
                'root' => $root,
                'files' => $files,
                'archive' => $file['name'] ?? null,
            ]
        );

        return ApiResponse::sendManualResponse($this->formatFileObject($file), 200);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/files/decompress',
        summary: 'Decompress archive',
        description: 'Extracts the specified archive on the server.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['file'],
                properties: [
                    new OA\Property(property: 'root', type: 'string', nullable: true),
                    new OA\Property(property: 'file', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Archive decompressed.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function decompress(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_ARCHIVE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        $root = $this->normalizeDirectory($payload['root'] ?? '/');
        $file = $this->normalizeRelativePath($payload['file'] ?? null);
        if ($file === null) {
            return $this->validationError('file', 'The file field is required.', 'required');
        }

        $wings = $this->createWingsClient($context['server']);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->decompressArchive((string) $context['server']['uuid'], $file, $root);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to decompress archive on Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error decompressing archive: ' . $e->getMessage());

            return $this->daemonErrorResponse(500);
        }

        if (!$response->isSuccessful()) {
            return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:file.decompress',
            [
                'root' => $root,
                'file' => $file,
            ]
        );

        return new Response('', 204);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/files/delete',
        summary: 'Delete files',
        description: 'Deletes files or directories from the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['root', 'files'],
                properties: [
                    new OA\Property(property: 'root', type: 'string'),
                    new OA\Property(property: 'files', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Files deleted.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function delete(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_DELETE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        $root = $this->normalizeDirectory($payload['root'] ?? '/');

        if (!isset($payload['files']) || !is_array($payload['files'])) {
            return $this->validationError('files', 'The files field is required.', 'required');
        }

        $files = $this->normalizeStringList($payload['files']);
        if (empty($files)) {
            return $this->validationError('files', 'At least one file must be provided.', 'min:1');
        }

        $wings = $this->createWingsClient($context['server']);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->deleteFiles((string) $context['server']['uuid'], $root, $files);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete files on Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error deleting files: ' . $e->getMessage());

            return $this->daemonErrorResponse(500);
        }

        if (!$response->isSuccessful()) {
            return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:file.delete',
            [
                'root' => $root,
                'files' => $files,
            ]
        );

        return new Response('', 204);
    }

    #[OA\Post(
        path: '/api/client/servers/{identifier}/files/create-folder',
        summary: 'Create directory',
        description: 'Creates a new directory on the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['root', 'name'],
                properties: [
                    new OA\Property(property: 'root', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Directory created.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 422, description: 'Validation error.'),
            new OA\Response(response: 502, description: 'Failed to communicate with Wings daemon.'),
        ]
    )]
    public function createFolder(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_CREATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError('payload', 'The request body must be valid JSON.', 'json');
        }

        $root = $this->normalizeDirectory($payload['root'] ?? '/');
        $name = isset($payload['name']) && is_string($payload['name']) ? trim($payload['name']) : '';

        if ($name === '') {
            return $this->validationError('name', 'The name field is required.', 'required');
        }

        $wings = $this->createWingsClient($context['server']);
        if ($wings instanceof Response) {
            return $wings;
        }

        try {
            $response = $wings->getServer()->createDirectory((string) $context['server']['uuid'], $name, $root);
        } catch (WingsConnectionException | WingsAuthenticationException | WingsRequestException $e) {
            App::getInstance(true)->getLogger()->error('Failed to create directory on Wings: ' . $e->getMessage());

            return $this->daemonErrorResponse(502);
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Unexpected error creating directory: ' . $e->getMessage());

            return $this->daemonErrorResponse(500);
        }

        if (!$response->isSuccessful()) {
            return $this->daemonErrorResponse($response->getStatusCode(), $response->getError());
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:file.create-folder',
            [
                'root' => $root,
                'name' => $name,
            ]
        );

        return new Response('', 204);
    }

    #[OA\Get(
        path: '/api/client/servers/{identifier}/files/upload',
        summary: 'Generate file upload URL',
        description: 'Generates a signed Wings upload URL for the specified server.',
        tags: ['Plugin - Pterodactyl API - Client Files'],
        parameters: [
            new OA\Parameter(
                name: 'identifier',
                in: 'path',
                required: true,
                description: 'Server UUID or short identifier.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Signed upload URL.', content: new OA\JsonContent(type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Forbidden or missing permission.'),
            new OA\Response(response: 404, description: 'Node configuration missing.'),
            new OA\Response(response: 500, description: 'Failed to generate upload URL.'),
        ]
    )]
    public function upload(Request $request, string $identifier): Response
    {
        $context = $this->resolveServerContext($request, $identifier);
        if ($context instanceof Response) {
            return $context;
        }

        $permissionCheck = $this->ensurePermission($context, SubuserPermissions::FILE_CREATE);
        if ($permissionCheck instanceof Response) {
            return $permissionCheck;
        }

        $node = Node::getNodeById((int) ($context['server']['node_id'] ?? 0));
        if (!$node) {
            return $this->daemonErrorResponse(500, 'Node configuration is missing for this server.');
        }

        $baseUrl = $this->buildWingsBaseUrl($node);

        try {
            $jwtService = $this->createJwtService($node);
            $url = $jwtService->getTokenGenerator()->generateFileUploadUrl(
                $baseUrl,
                (string) $context['server']['uuid'],
                (string) $context['user']['uuid']
            );
        } catch (\Throwable $e) {
            App::getInstance(true)->getLogger()->error('Failed to generate file upload URL: ' . $e->getMessage());

            return $this->daemonErrorResponse(500, 'Failed to generate upload URL.');
        }

        $this->logServerActivity(
            $request,
            $context,
            'server:file.upload',
            []
        );

        return ApiResponse::sendManualResponse([
            'object' => 'signed_url',
            'attributes' => [
                'url' => $url,
            ],
        ], 200);
    }

    private function normalizeDirectory(mixed $directory): string
    {
        if (!is_string($directory)) {
            return '/';
        }

        $path = trim($directory);
        if ($path === '') {
            return '/';
        }

        $path = str_replace('\\', '/', $path);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    private function normalizePath(mixed $path): ?string
    {
        if (!is_string($path)) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        $trimmed = str_replace('\\', '/', $trimmed);
        if (!str_starts_with($trimmed, '/')) {
            $trimmed = '/' . $trimmed;
        }

        return $trimmed;
    }

    private function normalizeRelativePath(mixed $path): ?string
    {
        if (!is_string($path)) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        $trimmed = str_replace('\\', '/', $trimmed);

        return ltrim($trimmed, '/');
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            $value = trim($item);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array{0: string, 1: array<int, string>}|null
     */
    private function deriveFilesFromLocation(string $location): ?array
    {
        $trimmed = trim($location);
        if ($trimmed === '' || str_ends_with($trimmed, '/')) {
            return null;
        }

        $normalized = str_replace('\\', '/', $trimmed);
        $directory = $this->normalizeDirectory(dirname($normalized));
        $fileName = basename($normalized);

        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            return null;
        }

        return [$directory, [$fileName]];
    }

    private function formatFileObject(array $file): array
    {
        $attributes = [
            'name' => (string) ($file['name'] ?? ''),
            'mode' => (string) ($file['mode'] ?? ''),
            'size' => (int) ($file['size'] ?? 0),
            'is_file' => $this->boolValue($file['is_file'] ?? false),
            'is_symlink' => $this->boolValue($file['is_symlink'] ?? false),
            'is_editable' => $this->boolValue($file['is_editable'] ?? false),
            'mimetype' => (string) ($file['mimetype'] ?? ''),
            'created_at' => $this->formatIso8601($file['created_at'] ?? null),
            'modified_at' => $this->formatIso8601($file['modified_at'] ?? null),
        ];

        return [
            'object' => 'file_object',
            'attributes' => $attributes,
        ];
    }

    private function validationError(string $field, string $detail, string $rule): Response
    {
        return ApiResponse::sendManualResponse([
            'errors' => [
                [
                    'code' => 'ValidationException',
                    'status' => '422',
                    'detail' => $detail,
                    'meta' => [
                        'source_field' => $field,
                        'rule' => $rule,
                    ],
                ],
            ],
        ], 422);
    }

    private function buildWingsBaseUrl(array $node): string
    {
        $scheme = (string) ($node['scheme'] ?? 'http');
        $host = (string) ($node['fqdn'] ?? '127.0.0.1');
        $port = (string) ($node['daemonListen'] ?? 8080);

        return rtrim($scheme . '://' . $host . ':' . $port, '/');
    }

    private function createJwtService(array $node): JwtService
    {
        $panelUrl = App::getInstance(true)->getConfig()->getSetting(
            ConfigInterface::APP_URL,
            'https://panel.mythical.systems'
        );

        $baseUrl = $this->buildWingsBaseUrl($node);

        return new JwtService(
            (string) ($node['daemon_token'] ?? ''),
            (string) $panelUrl,
            $baseUrl
        );
    }
}
