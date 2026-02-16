<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 *
 * OLSPanel Server Module developed by Jason Kenyon (https://nerdbyte.io)
 *
 * The OLSPanel Server Module is designed to integrate and manage the OLS (OpenLiteSpeed) server
 * within the FOSSBilling platform, providing users with a seamless experience in server
 * management, configuration, and performance monitoring.
 *
 * This module is not affiliated with, sponsored by, or endorsed by OLSPanel or FOSSBilling.
 * It is a community-maintained server module intended to enhance the capabilities of both platforms.
 *
 * For more details, updates, or to contribute, visit the GitHub repository:
 * https://github.com/NerdbyteIO/FOSSBilling-OLSPanel
 *
 * For additional information, visit the official project site: https://nerdbyte.io
 */

/**
 * OLSPanel Server Manager for FOSSBilling.
 *
 * Manages hosting accounts on OLSPanel control panel servers including
 * account creation, suspension, package changes, and SSO authentication.
 */
class Server_Manager_OLSPanel extends Server_Manager
{
    /**
     * Returns server manager configuration metadata.
     *
     * @return array configuration array with display label
     */
    public static function getForm(): array
    {
        return [
            "label" => "OLSPanel",
        ];
    }

    /**
     * Validates server manager configuration.
     * Ensures all required connection parameters are present.
     *
     * @throws Server_Exception if required configuration is missing
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
     * Returns the OLSPanel API port number.
     * Validates port is within valid range (1-65535) and defaults to 6844 if not configured.
     *
     * @return int the port number
     */
    public function getPort(): int
    {
        $port = $this->_config["port"] ?? 6844;

        if (
            $port !== null &&
            filter_var($port, FILTER_VALIDATE_INT) !== false &&
            $port > 0 &&
            $port <= 65535
        ) {
            return (int) $port;
        }

        // Default OLSPanel API port
        // See: https://github.com/NerdbyteIO/FOSSBilling-OLSPanel/issues/2
        return 6844;
    }

    /**
     * Returns the account management login URL.
     * Attempts SSO authentication if account is provided, otherwise returns standard login URL.
     *
     * @param Server_Account|null $account account for SSO login (optional)
     *
     * @return string login URL
     */
    public function getLoginUrl(?Server_Account $account = null): string
    {
        if ($account) {
            try {
                $response = $this->_request("sso_login", [
                    "username" => $account->getUsername(),
                ]);

                if (empty($response->url)) {
                    throw new Server_Exception("SSO response missing URL");
                }

                return $response->url;
            } catch (Server_Exception $e) {
                $this->getLog()->warning(
                    "SSO login failed for {$account->getUsername()}, falling back to standard URL: " .
                        $e->getMessage(),
                );
            }
        }

        return sprintf(
            "https://%s:%s/",
            $this->_config["host"],
            $this->getPort(),
        );
    }

    /**
     * Returns the reseller account management login URL.
     * OLSPanel uses the same URL for both resellers and regular accounts.
     *
     * @param Server_Account|null $account account for SSO login (optional)
     *
     * @return string login URL
     */
    public function getResellerLoginUrl(?Server_Account $account = null): string
    {
        return $this->getLoginUrl($account);
    }

    /**
     * Tests connectivity and authentication with the OLSPanel server.
     *
     * @return bool true if connection successful
     * @throws Server_Exception if connection or authentication fails
     */
    public function testConnection(): bool
    {
        $this->_request("packages_list", []);
        return true;
    }

    /**
     * Synchronizes account details with the OLSPanel server.
     * Currently returns a clone without performing server-side synchronization.
     *
     * @param Server_Account $account account to synchronize
     *
     * @return Server_Account synchronized account
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info(
            "Synchronizing account: {$account->getUsername()}",
        );

        // TODO: Implement server-side synchronization
        return clone $account;
    }

    /**
     * Creates a new hosting account on the OLSPanel server.
     *
     * @param Server_Account $account account to create
     *
     * @return bool true if account created successfully
     * @throws Server_Exception if package configuration invalid or creation fails
     */
    public function createAccount(Server_Account $account): bool
    {
        $username = $account->getUsername();
        $this->getLog()->info("Creating account: {$username}");

        $client = $account->getClient();
        $package = $account->getPackage();

        // Validate required package configuration
        $pkgId = $package->getCustomValue("pkg_id");
        if (empty($pkgId)) {
            throw new Server_Exception(
                "Package is missing required configuration: pkg_id",
            );
        }

        $phpVersion = $package->getCustomValue("php_version");
        if (empty($phpVersion)) {
            throw new Server_Exception(
                "Package is missing required configuration: php_version",
            );
        }

        $this->_request("add_user", [
            "username" => $username,
            "first_name" => trim((string) $client->getFullName()) ?: "User",
            "last_name" => " ", // OLSPanel requires non-empty last_name
            "email" => $client->getEmail(),
            "password" => $account->getPassword(),
            "domain" => $account->getDomain(),
            "pkg_id" => $pkgId,
            "php_version" => $phpVersion,
        ]);

        $this->getLog()->info("Account created: {$username}");
        return true;
    }

    /**
     * Suspends a hosting account on the OLSPanel server.
     *
     * @param Server_Account $account account to suspend
     *
     * @return bool true if account suspended successfully
     * @throws Server_Exception if suspension fails
     */
    public function suspendAccount(Server_Account $account): bool
    {
        $username = $account->getUsername();
        $this->getLog()->info("Suspending account: {$username}");

        $this->_request("suspend_user", [
            "username" => $username,
            "state" => "SUSPEND",
        ]);

        $this->getLog()->info("Account suspended: {$username}");
        return true;
    }

    /**
     * Unsuspends a hosting account on the OLSPanel server.
     *
     * @param Server_Account $account account to unsuspend
     *
     * @return bool true if account unsuspended successfully
     * @throws Server_Exception if unsuspension fails
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        $username = $account->getUsername();
        $this->getLog()->info("Unsuspending account: {$username}");

        $this->_request("suspend_user", [
            "username" => $username,
            "state" => "UNSUSPEND",
        ]);

        $this->getLog()->info("Account unsuspended: {$username}");
        return true;
    }

    /**
     * Permanently deletes a hosting account on the OLSPanel server.
     *
     * @param Server_Account $account account to cancel
     *
     * @return bool true if account cancelled successfully
     * @throws Server_Exception if cancellation fails
     */
    public function cancelAccount(Server_Account $account): bool
    {
        $username = $account->getUsername();
        $this->getLog()->info("Cancelling account: {$username}");

        $this->_request("suspend_user", [
            "username" => $username,
            "state" => "DELETE",
        ]);

        $this->getLog()->info("Account cancelled: {$username}");
        return true;
    }

    /**
     * Changes the hosting package for an account on the OLSPanel server.
     *
     * @param Server_Account $account account to modify
     * @param Server_Package $package new package to assign
     *
     * @return bool true if package changed successfully
     * @throws Server_Exception if package configuration invalid or change fails
     */
    public function changeAccountPackage(
        Server_Account $account,
        Server_Package $package,
    ): bool {
        $username = $account->getUsername();
        $this->getLog()->info("Changing package for account: {$username}");

        // Validate package configuration
        $pkgId = $package->getCustomValue("pkg_id");
        if (empty($pkgId)) {
            throw new Server_Exception(
                "Package is missing required configuration: pkg_id",
            );
        }

        $this->_request("update_user", [
            "username" => $username,
            "pkg_id" => $pkgId,
        ]);

        $this->getLog()->info("Package changed for account: {$username}");
        return true;
    }

    /**
     * Changes account username.
     * OLSPanel does not support this operation.
     *
     * @param Server_Account $account account to modify
     * @param string $newUsername new username
     *
     * @throws Server_Exception always throws as operation not supported
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
     * Changes account domain.
     * OLSPanel does not support this operation.
     *
     * @param Server_Account $account account to modify
     * @param string $newDomain new domain
     *
     * @throws Server_Exception always throws as operation not supported
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
     * Changes account password on the OLSPanel server.
     *
     * @param Server_Account $account account to modify
     * @param string $newPassword new password
     *
     * @return bool true if password changed successfully
     * @throws Server_Exception if password change fails
     */
    public function changeAccountPassword(
        Server_Account $account,
        string $newPassword,
    ): bool {
        $username = $account->getUsername();
        $this->getLog()->info("Changing password for account: {$username}");

        $this->_request("update_user", [
            "username" => $username,
            "password" => $newPassword,
        ]);

        $this->getLog()->info("Password changed for account: {$username}");
        return true;
    }

    /**
     * Changes account IP address.
     * OLSPanel does not support this operation.
     *
     * @param Server_Account $account account to modify
     * @param string $newIp new IP address
     *
     * @throws Server_Exception always throws as operation not supported
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

    /**
     * Executes an HTTP request to the OLSPanel API.
     * Handles authentication, validates responses, and provides centralized error handling.
     *
     * @param string $endpoint API endpoint to call
     * @param array|null $payload request payload data
     *
     * @return mixed decoded JSON response from API
     * @throws Server_Exception if request fails, returns invalid JSON, or API reports error
     */
    private function _request(string $endpoint, ?array $payload): mixed
    {
        $host = sprintf(
            "https://%s:%s/admin_api/%s/",
            $this->_config["host"],
            $this->getPort(),
            $endpoint,
        );

        try {
            $client = $this->getHttpClient()->withOptions([
                "verify_peer" => $this->_config["verify_ssl"] ?? true,
                "verify_host" => $this->_config["verify_ssl"] ?? true,
                "timeout" => $this->_config["timeout"] ?? 30,
            ]);

            $response = $client->request("POST", $host, [
                "headers" => [
                    "username" => $this->_config["username"],
                    "password" => $this->_config["password"],
                    "Content-Type" => "application/json",
                ],
                "body" => $payload,
            ]);

            // Validate HTTP status code
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new Server_Exception(
                    "OLSPanel API returned HTTP :status for endpoint :endpoint",
                    [":status" => $statusCode, ":endpoint" => $endpoint],
                );
            }

            $result = $response->getContent();

            // Validate response is not empty
            if (empty($result)) {
                throw new Server_Exception(
                    "OLSPanel API returned empty response for endpoint :endpoint",
                    [":endpoint" => $endpoint],
                );
            }

            // Parse and validate JSON response
            $data = json_decode($result);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Server_Exception(
                    "Invalid JSON from OLSPanel (:endpoint): :error",
                    [
                        ":endpoint" => $endpoint,
                        ":error" => json_last_error_msg(),
                    ],
                );
            }

            // Check for API-level errors in response
            if ($data?->error) {
                throw new Server_Exception(
                    "OLSPanel API error (:endpoint): :error",
                    [":endpoint" => $endpoint, ":error" => $data->error],
                );
            }

            if ($data?->success === false) {
                throw new Server_Exception(
                    "OLSPanel operation failed (:endpoint): :message",
                    [
                        ":endpoint" => $endpoint,
                        ":message" => $data->message ?? "Unknown error",
                    ],
                );
            }

            return $data;
        } catch (Server_Exception $e) {
            // Re-throw Server_Exception instances unchanged
            throw $e;
        } catch (\Exception $e) {
            // Wrap other exceptions (network errors, etc.)
            throw new Server_Exception(
                "Failed to communicate with OLSPanel (:endpoint): :error",
                [":endpoint" => $endpoint, ":error" => $e->getMessage()],
            );
        }
    }
}
