<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Manager_OLSPanel extends Server_Manager
{
    /**
     * Returns server manager parameters.
     *
     * @return array returns an array with the label of the server manager
     */
    public static function getForm(): array
    {
        return [
            "label" => "OLSPanel",
        ];
    }

    /**
     * Initializes the server manager.
     * Add required parameters checks here.
     */
    public function init()
    {
        if (empty($this->_config["host"])) {
            throw new Server_Exception(
                'The ":server_manager" server manager is not fully configured. Please configure the :missing',
                [":server_manager" => "OLSPanel", ":missing" => "hostname"],
                2001,
            );
        }

        if (empty($this->_config["username"])) {
            throw new Server_Exception(
                'The ":server_manager" server manager is not fully configured. Please configure the :missing',
                [":server_manager" => "OLSPanel", ":missing" => "username"],
                2001,
            );
        }

        if (empty($this->_config["password"])) {
            throw new Server_Exception(
                'The ":server_manager" server manager is not fully configured. Please configure the :missing',
                [
                    ":server_manager" => "OLSPanel",
                    ":missing" => "authentication credentials",
                ],
                2001,
            );
        }
    }

    /**
     * Returns the port number for the DirectAdmin server.
     * If the port is set in the configuration, verify that it's a valid port number (0 - 65535).
     * If a valid port is not set in the configuration, it defaults to '2222'.
     *
     * @return int|string the port number
     */
    public function getPort(): int|string
    {
        $port = $this->_config["port"];

        if (
            filter_var($port, FILTER_VALIDATE_INT) !== false &&
            $port >= 0 &&
            $port <= 65535
        ) {
            return $this->_config["port"];
        } else {
            /**
             * Per https://github.com/NerdbyteIO/FOSSBilling-OLSPanel/issues/2
             * Updating the default port from 5666 to 6844
             * You can still override this in settings.
             * If needed. :)
             */
            return 6844;
        }
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
        /**nLets check if there is an account, if so
         * we will go ahead and SSO login
         * the user.
         */
        if ($account) {
            $response = $this->_request("sso_login", [
                "username" => $account->getUsername(),
            ]);

            return $response->url;
        }
        // We are simply going to force https for now.
        // Perhaps down the rd, we will change this.
        return "https://" .
            $this->_config["host"] .
            ":" .
            $this->getPort() .
            "/";
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
        // We will simply attempt to grab the packages in OLSPanel
        // This will confirm if we have correct creds;

        $this->_request("packages_list", []);
        
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
        $this->getLog()->info(
            "Synchronizing account with server " . $account->getUsername(),
        );

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
        $package = $account->getPackage();

        $this->_request("add_user", [
            "username" => $account->getUsername(),
            "first_name" => trim((string) $client->getFullName()),
            "last_name" => " ", // We simply just pass a empty lastname, as its required.
            "email" => $client->getEmail(),
            "password" => $account->getPassword(),
            "domain" => $account->getDomain(),
            "pkg_id" => $package->getCustomValue("pkg_id"),
            "php_version" => $package->getCustomValue("php_version"),
        ]);

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
        $this->_request("suspend_user", [
            "username" => $account->getUsername(),
            "state" => "SUSPEND",
        ]);

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
        $this->_request("suspend_user", [
            "username" => $account->getUsername(),
            "state" => "UNSUSPEND",
        ]);

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
        $this->_request("suspend_user", [
            "username" => $account->getUsername(),
            "state" => "DELETE",
        ]);

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
    public function changeAccountPackage(
        Server_Account $account,
        Server_Package $package,
    ): bool {
        $this->_request("update_user", [
            "username" => $account->getUsername(),
            "pkg_id" => $package->getCustomValue("pkg_id"),
        ]);

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
    public function changeAccountUsername(
        Server_Account $account,
        string $newUsername,
    ): bool {
        throw new Server_Exception(":type: does not support :action:", [
            ":type:" => "OLSPanel",
            ":action:" => __trans("username changes"),
        ]);
    }

    /**
     * Changes the domain of an account on the server.
     *
     * @param Server_Account $account   the account for which the domain is to be changed
     * @param string         $newDomain the new domain
     *
     * @return bool returns true if the domain is successfully changed
     */
    public function changeAccountDomain(
        Server_Account $account,
        string $newDomain,
    ): bool {
        throw new Server_Exception(":type: does not support :action:", [
            ":type:" => "OLSPanel",
            ":action:" => __trans("changing the account domain"),
        ]);
    }

    /**
     * Changes the password of an account on the server.
     *
     * @param Server_Account $account     the account for which the password is to be changed
     * @param string         $newPassword the new password
     *
     * @return bool returns true if the password is successfully changed
     */
    public function changeAccountPassword(
        Server_Account $account,
        string $newPassword,
    ): bool {
        $this->_request("update_user", [
            "username" => $account->getUsername(),
            "password" => $newPassword,
        ]);

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
    public function changeAccountIp(
        Server_Account $account,
        string $newIp,
    ): bool {
        throw new Server_Exception(":type: does not support :action:", [
            ":type:" => "OLSPanel",
            ":action:" => __trans("changing the account IP"),
        ]);
    }

    private function _request(string $endpoint, ?array $payload): mixed
    {
        $host =
            "https://" .
            $this->_config["host"] .
            ":" .
            $this->getPort() .
            "/admin_api/" .
            $endpoint .
            "/";

        // Send POST query
        $client = $this->getHttpClient()->withOptions([
            "verify_peer" => false,
            "verify_host" => false,
            "timeout" => 30,
        ]);

        $response = $client->request("POST", $host, [
            "headers" => [
                "username" => $this->_config["username"],
                "password" => $this->_config["password"],
            ],
            "body" => $payload,
        ]);

        $result = $response->getContent();

        $data = json_decode($result);

        if ($data?->error) {
            throw new Server_Exception($data->error ?? "Something went wrong.");
        }

        if ($data?->success === false) {
            throw new Server_Exception(
                $data->message ?? "Something went wrong.",
            );
        }

        return $data;
    }
}
