# Oidc User Extension Sprinkle (UserFrosting 4.1)

Example sprinkle for extending the User class to contain additional fields.

# Installation

Edit UserFrosting `app/sprinkles.json` and add the following to the `require` list : `"userfrosting/oidc-user": "dev-master"`. Also add `oidc-user` to the `base` list. For example:

```
{
    "require": {
        "vuba/oidc-user": "dev-master"
    },
    "base": [
        "core",
        "account",
        "admin",
        "oidc-user"
    ]
}
```

### Update Composer

- Run `composer update` from the root project directory.

### Run migration

- Run `php bakery bake` from the root project directory.
