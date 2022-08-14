# TODO 

- how to : create installable extension instruction
- 


# Azure OAuth by Eblasoft

Authentication extension that allows EspoCRM to authenticate 
locally defined users with an OAuth provider. 


## Why this extension

Currently authentication with 2FA (TOTP and SMS) is already available. 
For some environments users want to have a single sign on experience. 
Although strictly spoken this is not SSO, we can delegate authentication to an OAuth server. 
Current version has been developed with 

## Prerequisites:

- a functioning EspoCRM installation.
- administration rights to install extensions
- an OAuth authentication server, like Azure AD or similar.

## Install : 

- download  .... 
- install the extension in the administration interface.

## Configuration : 

- You will need to create an application in Azure AD. You will need to set the callback url to https://mysite.com/oauth-callback.php
- From this definition you will get the application ID, tenant ID, client Secret.
-

## Credits and inspired by : 

- https://docs.espocrm.com/administration/2fa/

## Warnings and limitations
	
- This code has not yet been reviewed for security issues.
- This code is PoC level, not for production
- There is no authorisation mechanism for system or api users
- Users will not be synchronised. Users should be available and defined within EspoCRM

## Future ideas 

- Forward AAD groups to match groups/teams in Espo
