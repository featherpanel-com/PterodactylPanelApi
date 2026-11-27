<?php

/**
 * Mint a full-ACL Application API key for the parity CI admin user.
 * Usage (inside panel container): php /parity/mint-ptero-app-key.php [1|2]
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Acl\Api\AdminAcl;

require '/app/vendor/autoload.php';

$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$major = (int) ($argv[1] ?? 1);
$email = 'admin@parity.local';

$user = User::query()->where('email', $email)->first();
if ($user === null) {
    fwrite(STDERR, "User {$email} not found\n");
    exit(1);
}

$permissions = [
    'r_servers' => AdminAcl::READ | AdminAcl::WRITE,
    'r_nodes' => AdminAcl::READ | AdminAcl::WRITE,
    'r_allocations' => AdminAcl::READ | AdminAcl::WRITE,
    'r_users' => AdminAcl::READ | AdminAcl::WRITE,
    'r_locations' => AdminAcl::READ | AdminAcl::WRITE,
    'r_eggs' => AdminAcl::READ | AdminAcl::WRITE,
    'r_database_hosts' => AdminAcl::READ | AdminAcl::WRITE,
    'r_server_databases' => AdminAcl::READ | AdminAcl::WRITE,
];

if ($major === 1) {
    $permissions['r_nests'] = AdminAcl::READ | AdminAcl::WRITE;
}

$secret = Str::random(ApiKey::KEY_LENGTH);
$identifier = ApiKey::generateTokenIdentifier(ApiKey::TYPE_APPLICATION);

$attrs = array_merge($permissions, [
    'user_id' => $user->id,
    'key_type' => ApiKey::TYPE_APPLICATION,
    'identifier' => $identifier,
    'token' => Crypt::encrypt($secret),
    'memo' => 'parity-ci',
    'allowed_ips' => null,
]);

$key = ApiKey::query()->forceCreate($attrs);

echo $key->identifier . $secret;
