#NOTE:
This feature already implemented on Espocrm 7.5, and no more maintenance expected

# Azure OAuth by Eblasoft

Authentication extension that allows EspoCRM to authenticate 
locally defined users with an OAuth provider. 


## Why this extension

Currently authentication with 2FA (TOTP and SMS) is already available. 
For some environments users want to have a single sign on experience. 
This extension delegates the authentication of a user to an OAuth server. 
The current version and documentation has been developed and tested with Azure Active Directory in mind.

## Prerequisites:

- an installed EspoCRM installation.
- administration rights to install extensions
- an OAuth authentication server, like Azure AD or similar.
- your usernames should be equal to the main (full) email address of the identity used. 

# Install ( menu Administration > Extensions )

- lownload the latest release of the extension
- login in EspoCRM with administrator credentials
- install the extension in the administrative interface.

# OAuth Configuration ( menu Administration > Integrations > Azure )

- You will need to create an application in Azure AD. You will need to set the callback url to https://mysite.com/oauth-callback.php
- from this definition you will get the application ID, tenant ID, and client Secret.
- Enter the details in the configuration

# Set up ( menu Administration > Authentication )

After the OAuth configuration,  set up the authentication method to 
Oauth. This will enable the OAuth setting panel. 
You should select the Azure OAuth. Optionally you may force users to authenticate using OAuth.

When set up like this, you may both authenticate by OAuth and internal (ESPO) authentication. 

This allows you to test before you enforce it.

Note: when testing, you cannot use dual authentication when 2FA is enabled for that user.

For troubleshooting, set your loglevel in ESPO to "DEBUG"

# Logging out

** TODO ** Logging out will trigger a full logout:
https://docs.microsoft.com/en-us/azure/active-directory/develop/single-sign-out-saml-protocol

# Furthermore

## Credits and inspired by : 

- https://docs.espocrm.com/administration/2fa/

## Warnings and limitations

- This code under review for security issues.
- This code is PoC level, not yet ready for production
- There is no authorisation mechanism for system or api users
- Users will not be synchronised. Users should be available and defined within EspoCRM

## Future ideas 

- Forward AAD groups to match groups/teams in Espo
- store last token in Administration >  Auth Log
