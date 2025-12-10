# 1.0.9

## Fixed

- Fixed filter parameter extraction in controllers - now correctly handles nested query parameters like `filter[email]=value`
- Fixed WHERE clause building in controllers to properly use table aliases when JOINs are present
- Fixed user creation to generate secure random password when none is provided (required by User::createUser)
- Fixed include parameter parsing in ServersController (show and showExternal methods) to handle both comma-separated strings and arrays
- Fixed include parameter parsing in NodesController deployable method to handle array format from Paymenter
- Fixed allocations relationship to always return list structure when requested, even if empty
- Fixed subusers and databases relationships to only be included when explicitly requested via include parameter
- Removed resource limit validation checks (memory, disk, io, cpu minimums) to match Pterodactyl API behavior - now accepts 0 for unlimited values

## Improved

- Better compatibility with Paymenter and other Pterodactyl API clients
- All include parameters now properly supported for `/api/application/servers/external/{external_id}` endpoint

# 1.0.8

## Fixed

- Server extra allocations don't get unasinged!

# 1.0.6

## Added

- Added an informative widget that clearly displays and distinguishes between FeatherPanel and Pterodactyl Panel API keys, making it easy to understand that they are not cross compatible.
- Introduced full support for client API keys, allowing individual users to securely generate and manage their own Pterodactyl Panel API keys!

## Improved

- Using FeatherPanel native routing system now!
