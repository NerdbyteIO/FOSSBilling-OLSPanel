# FOSSBilling OLSPanel Server Module

This repository contains a server management module that integrates **OLSPanel** with **FOSSBilling**. It enables automated account provisioning and management for hosting services powered by OLSPanel.

This is a **community-developed and maintained** project and is not officially affiliated with FOSSBilling or OLSPanel.

---

## Features

The OLSPanel server module supports the following operations:

- Provision new users and domains
- Suspend and unsuspend user accounts
- Change account passwords
- Cancel and permanently delete user accounts

---

## Installation

1. Download the `OLSPanel.php` file from this repository.
2. Copy the file to the following location within your FOSSBilling installation:  
   `/library/server/manager/OLSPanel.php`
3. Log in to the FOSSBilling admin panel and configure a new server using the **OLSPanel** server manager.

---

## Custom Package Configuration (Required)

The following custom package values must be defined in FOSSBilling for proper operation:

- **pkg_id** – The Package ID from OLSPanel (see instructions below)
- **php_version** – The PHP version assigned to the package (for example: `8.1`, `8.2`)

These values are typically configured under:

**Products → Edit Product → Custom Parameters**

---

## How to Locate the Package ID in OLSPanel

To find the correct `pkg_id` value in OLSPanel:

1. Log in to your **OLSPanel Admin Panel**.
2. Navigate to **Users → Package**.
3. Click **Manage** on the package you wish to use.
4. Review the URL in your browser’s address bar. It will resemble the following:

   `https://yourolspaneldomain:panelport/whm/update_package/1/`

5. The final number in the URL represents the **Package ID**.  
   In the example above, `1` is the value that should be used for the `pkg_id` custom package parameter in FOSSBilling.

Ensure that:
- `yourolspaneldomain` is replaced with your actual OLSPanel domain or IP address
- `panelport` matches the port your OLSPanel instance is running on

---

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=NerdbyteIO/FOSSBilling-OLSPanel&type=Date)](https://star-history.com/#NerdbyteIO/FOSSBilling-OLSPanel&Date)

---

## Support the Project

If you find this server manager useful and would like to support ongoing development, consider buying me a coffee. Your support is greatly appreciated, but entirely optional.

<a href="https://www.buymeacoffee.com/jsonkenyon" target="_blank">
  <img src="https://cdn.buymeacoffee.com/buttons/default-orange.png" alt="Buy Me A Coffee" height="41" width="174">
</a>
