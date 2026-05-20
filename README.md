# PterodactylPanelApi Plugin

A comprehensive Pterodactyl Panel API implementation for FeatherPanel that provides full compatibility with Pterodactyl's Application API endpoints.

## Supported API Endpoints

### 🔐 Authentication

All endpoints require a valid API key to be provided in the `Authorization` header as a Bearer token.

### 👥 User Management

| Endpoint                                        | Method | Description                   | Auth Required | Example Path                             |
| ----------------------------------------------- | ------ | ----------------------------- | ------------- | ---------------------------------------- |
| `/api/application/users`                        | GET    | List all users                | Yes (API Key) | `/api/application/users`                 |
| `/api/application/users/{user}`                 | GET    | View user by ID               | Yes (API Key) | `/api/application/users/42`              |
| `/api/application/users/external/{external_id}` | GET    | View user by external ID      | Yes (API Key) | `/api/application/users/external/abc123` |
| `/api/application/users`                        | POST   | Create a new user             | Yes (API Key) | `/api/application/users`                 |
| `/api/application/users/{user}`                 | PATCH  | Update an existing user by ID | Yes (API Key) | `/api/application/users/42`              |
| `/api/application/users/{user}`                 | DELETE | Delete a user by ID           | Yes (API Key) | `/api/application/users/42`              |

### 🖥️ Server Management

| Endpoint                                          | Method | Description                         | Auth Required | Example Path                               |
| ------------------------------------------------- | ------ | ----------------------------------- | ------------- | ------------------------------------------ |
| `/api/application/servers`                        | GET    | List all servers                    | Yes (API Key) | `/api/application/servers`                 |
| `/api/application/servers`                        | POST   | Create a new server                 | Yes (API Key) | `/api/application/servers`                 |
| `/api/application/servers/{server}`               | GET    | Get server details by ID            | Yes (API Key) | `/api/application/servers/42`              |
| `/api/application/servers/external/{external_id}` | GET    | Get server details by external ID   | Yes (API Key) | `/api/application/servers/external/abc123` |
| `/api/application/servers/{server}/suspend`       | POST   | Suspend a server                    | Yes (API Key) | `/api/application/servers/42/suspend`      |
| `/api/application/servers/{server}/unsuspend`     | POST   | Unsuspend a server                  | Yes (API Key) | `/api/application/servers/42/unsuspend`    |
| `/api/application/servers/{server}/reinstall`     | POST   | Reinstall a server                  | Yes (API Key) | `/api/application/servers/42/reinstall`    |
| `/api/application/servers/{server}`               | DELETE | Delete a server                     | Yes (API Key) | `/api/application/servers/42`              |
| `/api/application/servers/{server}/details`       | PATCH  | Update server details               | Yes (API Key) | `/api/application/servers/42/details`      |
| `/api/application/servers/{server}/build`         | PATCH  | Update server build configuration   | Yes (API Key) | `/api/application/servers/42/build`        |
| `/api/application/servers/{server}/startup`       | PATCH  | Update server startup configuration | Yes (API Key) | `/api/application/servers/42/startup`      |

### 🌍 Location Management

| Endpoint                                  | Method | Description             | Auth Required | Example Path                   |
| ----------------------------------------- | ------ | ----------------------- | ------------- | ------------------------------ |
| `/api/application/locations`              | GET    | List all locations      | Yes (API Key) | `/api/application/locations`   |
| `/api/application/locations/{locationId}` | GET    | Get location details    | Yes (API Key) | `/api/application/locations/1` |
| `/api/application/locations`              | POST   | Create a new location   | Yes (API Key) | `/api/application/locations`   |
| `/api/application/locations/{locationId}` | PATCH  | Update location details | Yes (API Key) | `/api/application/locations/1` |
| `/api/application/locations/{locationId}` | DELETE | Delete a location       | Yes (API Key) | `/api/application/locations/1` |

### 🖥️ Node Management

| Endpoint                                                     | Method | Description                  | Auth Required | Example Path                             |
| ------------------------------------------------------------ | ------ | ---------------------------- | ------------- | ---------------------------------------- |
| `/api/application/nodes`                                     | GET    | List all nodes               | Yes (API Key) | `/api/application/nodes`                 |
| `/api/application/nodes/{nodeId}`                            | GET    | Get node details             | Yes (API Key) | `/api/application/nodes/1`               |
| `/api/application/nodes/deployable`                          | GET    | Get deployable nodes         | Yes (API Key) | `/api/application/nodes/deployable`      |
| `/api/application/nodes`                                     | POST   | Create a new node            | Yes (API Key) | `/api/application/nodes`                 |
| `/api/application/nodes/{nodeId}`                            | PATCH  | Update node configuration    | Yes (API Key) | `/api/application/nodes/1`               |
| `/api/application/nodes/{nodeId}`                            | DELETE | Delete a node                | Yes (API Key) | `/api/application/nodes/1`               |
| `/api/application/nodes/{nodeId}/configuration`              | GET    | Get node Wings configuration | Yes (API Key) | `/api/application/nodes/1/configuration` |
| `/api/application/nodes/{nodeId}/allocations`                | GET    | List node allocations        | Yes (API Key) | `/api/application/nodes/1/allocations`   |
| `/api/application/nodes/{nodeId}/allocations`                | POST   | Create node allocations      | Yes (API Key) | `/api/application/nodes/1/allocations`   |
| `/api/application/nodes/{nodeId}/allocations/{allocationId}` | DELETE | Delete node allocation       | Yes (API Key) | `/api/application/nodes/1/allocations/5` |

### 🏠 Nest & Egg Management

| Endpoint                                       | Method | Description      | Auth Required | Example Path                      |
| ---------------------------------------------- | ------ | ---------------- | ------------- | --------------------------------- |
| `/api/application/nests`                       | GET    | List all nests   | Yes (API Key) | `/api/application/nests`          |
| `/api/application/nests/{nestId}`              | GET    | Get nest details | Yes (API Key) | `/api/application/nests/1`        |
| `/api/application/nests/{nestId}/eggs`         | GET    | List nest eggs   | Yes (API Key) | `/api/application/nests/1/eggs`   |
| `/api/application/nests/{nestId}/eggs/{eggId}` | GET    | Get egg details  | Yes (API Key) | `/api/application/nests/1/eggs/5` |

### 🗄️ Database Management

| Endpoint                                                                    | Method | Description             | Auth Required | Example Path                                             |
| --------------------------------------------------------------------------- | ------ | ----------------------- | ------------- | -------------------------------------------------------- |
| `/api/application/servers/{serverId}/databases`                             | GET    | List server databases   | Yes (API Key) | `/api/application/servers/42/databases`                  |
| `/api/application/servers/{serverId}/databases/{databaseId}`                | GET    | Get database details    | Yes (API Key) | `/api/application/servers/42/databases/1`                |
| `/api/application/servers/{serverId}/databases`                             | POST   | Create server database  | Yes (API Key) | `/api/application/servers/42/databases`                  |
| `/api/application/servers/{serverId}/databases/{databaseId}`                | PATCH  | Update database         | Yes (API Key) | `/api/application/servers/42/databases/1`                |
| `/api/application/servers/{serverId}/databases/{databaseId}/reset-password` | POST   | Reset database password | Yes (API Key) | `/api/application/servers/42/databases/1/reset-password` |
| `/api/application/servers/{serverId}/databases/{databaseId}`                | DELETE | Delete database         | Yes (API Key) | `/api/application/servers/42/databases/1`                |

### 🔑 Admin API Key Management

| Endpoint                                 | Method    | Description         | Auth Required | Example Path                          |
| ---------------------------------------- | --------- | ------------------- | ------------- | ------------------------------------- |
| `/api/pterodactylpanelapi/api-keys`      | GET       | List all API keys   | Yes (Admin)   | `/api/pterodactylpanelapi/api-keys`   |
| `/api/pterodactylpanelapi/api-keys/{id}` | GET       | Get API key details | Yes (Admin)   | `/api/pterodactylpanelapi/api-keys/1` |
| `/api/pterodactylpanelapi/api-keys`      | POST      | Create new API key  | Yes (Admin)   | `/api/pterodactylpanelapi/api-keys`   |
| `/api/pterodactylpanelapi/api-keys/{id}` | PUT/PATCH | Update API key      | Yes (Admin)   | `/api/pterodactylpanelapi/api-keys/1` |
| `/api/pterodactylpanelapi/api-keys/{id}` | DELETE    | Delete API key      | Yes (Admin)   | `/api/pterodactylpanelapi/api-keys/1` |

### ⚙️ Plugin Settings

| Endpoint                            | Method | Description         | Auth Required | Example Path                        |
| ----------------------------------- | ------ | ------------------- | ------------- | ----------------------------------- |
| `/api/pterodactylpanelapi-settings` | GET    | Get plugin settings | Yes (Admin)   | `/api/pterodactylpanelapi-settings` |

## Features

- ✅ **Full Pterodactyl API Compatibility**: Complete implementation of Pterodactyl's Application API
- ✅ **Pagination Support**: All list endpoints support pagination with `page` and `per_page` parameters
- ✅ **Relationship Loading**: Support for `include` parameter to load related resources
- ✅ **Comprehensive CRUD Operations**: Create, Read, Update, Delete for all resources
- ✅ **Advanced Server Management**: Suspend, unsuspend, reinstall, and detailed configuration updates
- ✅ **Node & Allocation Management**: Complete node lifecycle management with allocation handling
- ✅ **Database Management**: Full server database CRUD with password management
- ✅ **Nest & Egg Management**: Server type and configuration management
- ✅ **Location Management**: Geographic location organization
- ✅ **User Management**: Complete user lifecycle with external ID support
- ✅ **API Key Management**: Admin interface for API key creation and management
- ✅ **Environment Variables**: Proper handling of server environment variables
- ✅ **Wings Integration**: Node configuration generation for Wings daemon
- ✅ **Error Handling**: Comprehensive error responses following Pterodactyl standards
- ✅ **Activity Logging**: All operations are logged for audit purposes
- ✅ **Email Notifications**: Automated email notifications for server operations

## Authentication

All Application API endpoints require a valid API key to be provided in the `Authorization` header as a Bearer token:

```bash
Authorization: Bearer ptla_YOUR_API_KEY
Accept: Application/vnd.pterodactyl.v1+json
```

Admin endpoints require admin authentication with appropriate permissions.
