<h1 align="center">Narrowspark Automatic Configurators</h1>

Narrowspark Automatic Configurations allow the automation of Composer packages configuration via the
[`Narrowspark Automatic`](../README.md) Composer plugin.

You can choose 2 ways to create some configurators, you can store the configurators on their own repositories, outside of your Composer package repository or inside your Composer package repository.

Creating Configurators Repository
----------------
Narrowspark Automatic checks all packages for the `automatic-configurator` package type and register it to Automatic.

Creating Package Configurators
----------------
Add a new key `custom-configurators` to the `automatic.json` file or to `composer.json extra automatic` section to register new Package Configurators.
