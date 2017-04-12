# Ride: Web ORM Assets

This module adds the backend for assets to the Ride web application.

## User Chrooting

You can chroot users in their own folder by granting the `assets.chroot` permission to a user.

This will create a  `Users` folder in the root directory which is the parent for all chrooted folders.
A user's folder has the username as name.

## Related Modules 

- [ride/app](https://github.com/all-ride/ride-app)
- [ride/app-orm](https://github.com/all-ride/ride-app-orm)
- [ride/app-orm-asset](https://github.com/all-ride/ride-app-orm-asset)
- [ride/lib-form](https://github.com/all-ride/ride-lib-form)
- [ride/lib-http](https://github.com/all-ride/ride-lib-http)
- [ride/lib-image](https://github.com/all-ride/ride-lib-image)
- [ride/lib-media](https://github.com/all-ride/ride-lib-media)
- [ride/lib-orm](https://github.com/all-ride/ride-lib-orm)
- [ride/lib-system](https://github.com/all-ride/ride-lib-system)
- [ride/lib-validation](https://github.com/all-ride/ride-lib-validation)
- [ride/web](https://github.com/all-ride/ride-web)
- [ride/web-base](https://github.com/all-ride/ride-web-base)
- [ride/web-form](https://github.com/all-ride/ride-web-form)

## Installation

You can use [Composer](http://getcomposer.org) to install this application.

```
composer require ride/wba-assets
```
