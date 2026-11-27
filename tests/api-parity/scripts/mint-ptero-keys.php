<?php

/**
 * Mint Application (ptla_) + Client (ptlc_) API keys for parity CI.
 * Usage (inside panel container): php /tmp/mint-ptero-keys.php [1|2]
 * Prints JSON: {"application":"ptla_...","client":"ptlc_..."}
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

function mintKey(User $user, int $type, array $extra = []): string
{
    $secret = Str::random(ApiKey::KEY_LENGTH);
    $identifier = ApiKey::generateTokenIdentifier($type);
    $attrs = array_merge($extra, [
        'user_id' => $user->id,
        'key_type' => $type,
        'identifier' => $identifier,
        'token' => Crypt::encrypt($secret),
        'memo' => 'parity-ci',
        'allowed_ips' => null,
    ]);
    ApiKey::query()->forceCreate($attrs);

    return $identifier . $secret;
}

$appPerms = [
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
    $appPerms['r_nests'] = AdminAcl::READ | AdminAcl::WRITE;
}

$application = mintKey($user, ApiKey::TYPE_APPLICATION, $appPerms);
$client = mintKey($user, ApiKey::TYPE_ACCOUNT);

echo json_encode([
    'application' => $application,
    'client' => $client,
], JSON_UNESCAPED_SLASHES);
