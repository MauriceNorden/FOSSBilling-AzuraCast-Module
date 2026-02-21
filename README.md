# FOSSBilling-AzuraCast-Module
Module for managing AzuraCast via FOSSBilling

# Intro
This is a work in progress project.
Used software versions:
AzuraCast: v0.23.2
FOSSBilling: 0.7.2

# What does work:
- Adding a server.
- Testing server connection
- Creating a new station, account, and role.
- Creating custom roles based on custom hosting plan variables.
- Changing password of account.
- Logging in with a login token.

# What doesn't work:
- Changing packages
- Suspending and unsuspending
- Changing usernames
- Removing accounts stations and roles.
- Syncing accounts

# How to install alpha:
Place `AzuraCast.php` in `/var/www/html/library/Server/Manager/`

# How to add AzuraCast instance in FOSSBilling:
## In AzuraCast:
- Login to your AzuraCast instance as admin in the webUI.
- Click on your email in the top right corner
- Click on `My Account`
- Click on `Add API-Key`
- Give your Key a name
- Save your API key on a secure location

## In FOSSBilling:
- Login to your FOSSBilling instance as admin in the WebUI
- Navigate to `System->Hosting subscriptions and servers`
- Click on `New Server`
- Fill in the following default fields:
    - Name: (Friendly name)
    - Hostname: (Domain name / IP of AzuraCast Instance)
    - IP: (Domain name / IP of AzuraCast Instance)
    - Admin API Token: (Your Generated API token)
    - ConnectionPort: (Port of your AzuraCast instance `80` for HTTP or `443` for HTTPS)

- Save the form and test the Connection.

# Docs - Creating custom plans:
Custom plans can be applied **only when creating a new account** at this time.
Adding the custom values can be done in FOSSbilling at `System-> Hosting Subscriptions and Servers-> New plan-> Servermanager specific parameters `

| Property    | type |
| -------- | ------- |
| administerAll  | Boolean   |
| viewStationManagement | Boolean     |
| viewStationReports | Boolean   |
| viewStationLogs | Boolean   |
| manageStationProfile | Boolean   |
| manageStationBroadcasting | Boolean   |
| manageStationStreamers| Boolean   |
| manageStationMounts| Boolean   |
| manageStationRemotes| Boolean   |
| manageStationMedia| Boolean   |
| deleteStationMedia | Boolean   |
| manageStationAutomation| Boolean   |
| manageStationWebhooks| Boolean   |
| manageStationPodcasts| Boolean   |


# Docs - Inner workings of the AzuraCast server manager
When creating a new station after the order comes, the id of the AzuraCast user will be written to aid table in FOSSBilling

When removing a station, the role and the station will be removed NOT the user, Because there is a change that the user has another station on the same account.
The comparison will be done on "Domain Name"