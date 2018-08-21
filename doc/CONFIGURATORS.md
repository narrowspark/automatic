<h1 align="center">Narrowspark Automatic Configurators</h1>

Narrowspark Automatic Configurations allow the automation of Composer packages configuration via the
[`Narrowspark Automatic`](../README.md) Composer plugin.

Configurators
----------------
Configurators define the different tasks executed when installing a dependency, such as running commands, copying files or adding new environment variables.

The package only contain the tasks needed to install and configure the dependency, because Narrowspark Automatic Configurators are smart enough to reverse those tasks when uninstalling and unconfiguring the dependencies.

Narrowspark Automatic comes with several types of tasks, which are called **configurators**: `copy`, `env`, `composer-scripts`, `gitignore`, and `post-install-output`.

You can choose 2 ways to create some configurators, you can store the configurators on their own repositories, outside of your Composer package repository or inside your Composer package repository.

Creating Configurators Repository
----------------
Narrowspark Automatic checks all packages for the `automatic-configurator` package type and register it to Automatic.

Creating Package Configurators
----------------
Add a new key `custom-configurators` to the `automatic.json` file or to `composer.json extra automatic` section to register new Package Configurators.
