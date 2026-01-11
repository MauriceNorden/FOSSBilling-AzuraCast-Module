<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Manager_AzuraCast extends Server_Manager
{
    /**
     * Returns server manager parameters.
     *
     * @return array returns an array with the label of the server manager
     */
    public static function getForm(): array
    {
        return [
            'label' => 'AzuraCast',
            'form' => [
                'credentials' => [
                    'fields' => [
                        [
                            'name' => 'accesshash',
                            'type' => 'password',
                            'label' => 'Admin API Token',
                            'placeholder' => 'API key of admin user you\'ve generated in AzuraCast',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Initializes the server manager.
     * Add required parameters checks here.
     */
    public function init()
    {
    }


    /**
     * Returns the URL for account management.
     *
     * @param Server_Account|null $account the account for which the URL is generated
     *
     * @return string returns the URL as a string
     */
    public function getLoginUrl(?Server_Account $account = null): string
    {
        return 'https://' . $this->_config['host'] . ':' . $this->getPort() . '/';
    }

    public function getPort(): int|string
    {
        $port = $this->_config['port'];

        if (filter_var($port, FILTER_VALIDATE_INT) !== false && $port >= 0 && $port <= 65535) {
            return $this->_config['port'];
        } else {
            return 443;
        }
    }

    /**
     * Returns the URL for reseller account management.
     *
     * @param Server_Account|null $account the account for which the URL is generated
     *
     * @return string returns the URL as a string
     */
    public function getResellerLoginUrl(?Server_Account $account = null): string
    {
        return $this->getLoginUrl();
    }

    /**
     * Tests the connection to the server.
     *
     * @return bool returns true if the connection is successful
     */
   public function testConnection(): bool
{
    $services = $this->request('GET', '/api/admin/services');

    if (!is_array($services) || count($services) < 1) {
        throw new Server_Exception(
            'Failed to connect to the :type: server',
            [':type:' => 'AzuraCast']
        );
    }

    return true;
}

    /**
     * Synchronizes the account with the server.
     *
     * @param Server_Account $account the account to be synchronized
     *
     * @return Server_Account returns the synchronized account
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info('Synchronizing account with server ' . $account->getUsername());

        // @example - retrieve username from server and set it to cloned object
        // $new->setUsername('newusername');
        return clone $account;
    }

    /**
     * Creates a new account on the server.
     *
     * @param Server_Account $account the account to be created
     *
     * @return bool returns true if the account is successfully created
     */

    public function createAccount(Server_Account $account): bool
{
    $client = $account->getClient();

    /** 1. Station aanmaken */
    $station = $this->request('POST', '/api/admin/stations', [
        'name' => $account->getDomain(),
    ]);

    if (!isset($station['id'])) {
        throw new Server_Exception('Failed to create AzuraCast station');
    }

    $stationId = $station['id'];

    /** 2. Role aanmaken (alleen voor dit station) */
    $role = $this->request('POST', '/api/admin/roles', [
        'name' => 'station_' . $stationId,
        'permissions' => [
            'global' => [],
            'station' => [
                [
                    'id' => $stationId,
                    'permissions' => ['administer all'],
                ],
            ],
        ],
    ]);

    if (!isset($role['id'])) {
        throw new Server_Exception('Failed to create AzuraCast role');
    }



    /** 3. User aanmaken */
    $user = $this->request('POST', '/api/admin/users', [
        'email' => $client->getEmail(),
        'name'  => trim((string) $client->getFullName()),
        'roles' => [(string) $role['id']],
    ]);

    if (!isset($user['id'])) {
        throw new Server_Exception('Failed to create AzuraCast user');
    }
 $this->getLog()->info('Creating shared hosting account');
    return true;
}


    /**
     * Suspends an account on the server.
     *
     * @param Server_Account $account the account to be suspended
     *
     * @return bool returns true if the account is successfully suspended
     */
    public function suspendAccount(Server_Account $account): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Suspending reseller hosting account');
        } else {
            $this->getLog()->info('Suspending shared hosting account');
        }

        return true;
    }

    /**
     * Unsuspends an account on the server.
     *
     * @param Server_Account $account the account to be unsuspended
     *
     * @return bool returns true if the account is successfully unsuspended
     */


    public function unsuspendAccount(Server_Account $account): bool
{
    $stationId = $account->getExternalId();

    if (!$stationId) {
        throw new Server_Exception('Missing AzuraCast station ID');
    }

    $this->request(
        'PUT',
        '/api/admin/station/' . $stationId,
        ['is_enabled' => true]
    );
    $this->getLog()->info('Unsuspending shared hosting account');

    return true;
}


    /**
     * Cancels an account on the server.
     *
     * @param Server_Account $account the account to be cancelled
     *
     * @return bool returns true if the account is successfully cancelled
     */
public function cancelAccount(Server_Account $account): bool
{
    $stationId = $account->getMeta('azuracast_station_id');
    $userId    = $account->getMeta('azuracast_user_id');
    $roleId    = $account->getMeta('azuracast_role_id');

    if ($userId) {
        $this->request('DELETE', '/api/admin/user/' . $userId);
    }

    if ($roleId) {
        $this->request('DELETE', '/api/admin/role/' . $roleId);
    }

    if ($stationId) {
        $this->request('DELETE', '/api/admin/station/' . $stationId);
    }

    return true;
}


    /**
     * Changes the package of an account on the server.
     *
     * @param Server_Account $account the account for which the package is to be changed
     * @param Server_Package $package the new package
     *
     * @return bool returns true if the package is successfully changed
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Updating reseller hosting account');
        } else {
            $this->getLog()->info('Updating shared hosting account');
        }

        $package->getName();
        $package->getQuota();
        $package->getBandwidth();
        $package->getMaxSubdomains();
        $package->getMaxParkedDomains();
        $package->getMaxDomains();
        $package->getMaxFtp();
        $package->getMaxSql();
        $package->getMaxPop();

        $package->getCustomValue('param_name');

        return true;
    }

    /**
     * Changes the username of an account on the server.
     *
     * @param Server_Account $account     the account for which the username is to be changed
     * @param string         $newUsername the new username
     *
     * @return bool returns true if the username is successfully changed
     */
    public function changeAccountUsername(Server_Account $account, string $newUsername): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Changing reseller hosting account username');
        } else {
            $this->getLog()->info('Changing shared hosting account username');
        }

        return true;
    }

    /**
     * Changes the domain of an account on the server.
     *
     * @param Server_Account $account   the account for which the domain is to be changed
     * @param string         $newDomain the new domain
     *
     * @return bool returns true if the domain is successfully changed
     */
    public function changeAccountDomain(Server_Account $account, string $newDomain): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Changing reseller hosting account domain');
        } else {
            $this->getLog()->info('Changing shared hosting account domain');
        }

        return true;
    }

    /**
     * Changes the password of an account on the server.
     *
     * @param Server_Account $account     the account for which the password is to be changed
     * @param string         $newPassword the new password
     *
     * @return bool returns true if the password is successfully changed
     */
public function changeAccountPassword(Server_Account $account, string $newPassword): bool
{
    $client = $account->getClient();
    $userId = $this->getMeta($client->getEmail())['azuracast_user_id'];

    if (!$userId) {
        throw new Server_Exception('Missing AzuraCast user ID');
    }

    $this->request('PUT', '/api/admin/user/' . $userId, [
        'auth_password' => $newPassword,
    ]);

    $this->getLog()->info('Changing reseller hosting account password');

    return true;
}
    /**
     * Changes the IP of an account on the server.
     *
     * @param Server_Account $account the account for which the IP is to be changed
     * @param string         $newIp   the new IP
     *
     * @return bool returns true if the IP is successfully changed
     */
    public function changeAccountIp(Server_Account $account, string $newIp): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Changing reseller hosting account ip');
        } else {
            $this->getLog()->info('Changing shared hosting account ip');
        }

        return true;
    }


    /**
     * Seperate function to fetch id of user, station and role based on email given in function.
     * 
     */

private function getMeta(
    ){


    $client = $account->getClient();
    $userId = $this->getMeta($client->getEmail())['azuracast_user_id'];
    //working
    $users = $this->request('GET', '/api/admin/users', []);
    $roles = $this->request('GET', '/api/admin/roles', []);


    $roleIds = [];
    $stationIds = [];
    $userId = null;

    /** 1️⃣ Gebruiker zoeken */
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            $userId = $user['id'];

            foreach ($user['roles'] as $role) {
                $roleIds[] = $role['id'];
            }
            break;
        }
    }

    if ($userId === null) {
        return null; // geen gebruiker gevonden
    }

    /** 2️⃣ Station IDs ophalen via rollen */
    foreach ($roles as $role) {
        if (in_array($role['id'], $roleIds, true)) {
            if (!empty($role['permissions']['station'])) {
                foreach ($role['permissions']['station'] as $station) {
                    $stationIds[] = $station['id'];
                }
            }
        }
    }

    /** 3️⃣ Uniek maken */
    $stationIds = array_values(array_unique($stationIds));

    return [
        'azuracast_user_id' => $userId,
        'azuracast_role_id' => $roleIds,
        'azuracast_station_id' => $stationIds,
    ];
}



 
/**
 * Send REST request to AzuraCast API
 *
 * @throws Server_Exception
 */
private function request(
    string $method,
    string $endpoint,
    array $payload = null
): mixed {

    $url = 'https://' . $this->_config['host'] . ':' . $this->getPort() . $endpoint;
    $client = $this->getHttpClient()->withOptions([
        'auth_bearer' => $this->_config['accesshash'],
        'verify_peer' => false,
        'verify_host' => false,
        'timeout' => 30,
    ]);


           
    if ($payload !== null) {
        $options['json'] = $payload;

}


    $response = $client->request($method, $url, [
        'body' => $payload
    ]);

    $status = $response->getStatusCode();
    $body   = $response->getContent(false);

    if ($status < 200 || $status >= 300) {
        throw new Server_Exception(
            '<:key:>(:url:) AzuraCast API request failed (:status:): :body:',
            [
                ':status:' => $status,
                ':body:'   => $body,
                ':url:' => $url,
                ':key:' => $this->_config['accesshash'],
            ]
        );
    }

    $decoded = json_decode($body, true);

    if ($decoded === null && $body !== '') {
        throw new Server_Exception(
            'Invalid JSON response from AzuraCast API'
        );
    }

    return $decoded ?? [];


}}