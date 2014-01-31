# Drupal Distribution Manager

This project is a command line tool that creates and manages Drupal
distributions with a focus on continuous integration. Distros are pre-configured
to integrate seamlessly with [Travis CI](https://travis-ci.org/) and are bundled
with [Apache Ant](http://ant.apache.org/) targets that make it easy to build up,
tear down, and run tests against your application.

## Installation

```
curl -sLO https://github.com/cpliakas/drupal-distro/releases/download/0.2.0/drupal-distro.phar
```

Run `php drupal-distro.phar list` to see all commands supported by this tool and
ensure that installation succeeded.

It is also common practice to place the `drupal-distro.phar` file in a location
that makes it easier to access, for example `/usr/local/bin`, and renaming it
to `drupal-distro`. Ensure the file is executable by running `chmod 755` so that
you don't have to prefix the command with `php`.

## Usage For Distro Maintainers

#### Create A New Distro

```
php drupal-distro.phar new \
  --profile-name="My Distro" \
  --profile-description="A test installation profile" \
  --git-url=http://example.com/repo.git \
  distro_test
```

_TIP_: To test locally, create a repo on your filesystem by running
`git init --bare /path/to/repo/dir` and then using `file:///path/to/repo/dir`
as the `--git-url` option.

#### Push Distro To Git Repository

```
cd distro_test
git push -u origin master
```

## Usage For Distro Developers

Clone the repository and change into the resulting directory. It is assumed that your web and
database servers are configured to serve your Drupal application.

#### Install The Distro

* Copy the `build.properties.dist` file to `build.properties`
* Modify `build.properties` according to your local environment

```ini
base.url=http://localhost
db.url=mysql://username:password@host/db

#sites.subdir=default
#site.mail=test@example.com
#account.name=admin
#account.pass=admin
#account.mail=test@example.com
```

* Run `ant` on the command line

#### Work With A Forked Repo

It is common practice to develop against a fork of the primary repo or a development branch.
The recommended workflow is to create a secondary makefile that references the repository or
branch being developed against and modify the `build.properties` file to use the newly
created makefile.

For example, the steps below assume a primary makefile named `example.make`.

* Copy `example.make` to `example-dev.make`
* Modify `example-dev.make` to reflect your forked repo or development branch
* Add the following directive to `build.properties`

```ini
drush.makefile=example-dev.make
```

* Run `ant` on the command line

#### Write The Behavior Tests

Behavior tests are contained in the `test` directory. Refer to the
[Behat](http://behat.org/) project for more details on writing tests. The Apache
Ant targets included with your new distribution will automatically install the
test suite (Behat + Mink + Selenium), and your distro is pre-configured to use
the tools.

#### Run The Behavior Tests

* Run `ant run-tests` to re-install the distro and run the behavior tests
  * Pass the `-Ddrush.nomake=1` option to prevent rebuilding the docroot
  * Pass the `-Ddrush.noinstall=1` option to prevent reinstalling the distro

#### Continuous Integration

Your new distro is pre-configured to work with Travis CI. The easiest way to get
started is to [create a repository on GitHub](https://help.github.com/articles/create-a-repo),
log into Travis CI with your GitHub account, and then enable your repository for
testing. All commits pushed to GitHub will automatically trigger a Travis CI
build and run your behavior tests.

## Related Projects

Drupal Distribution Manager builds on the shoulders of giants, and the list of
projects below are used by this application:

* [Drupal](https://drupal.org)
* [Drush](https://github.com/drush-ops/drush)
* [Behat](http://behat.org/)
* [Drupal Behat Extension](https://github.com/jhedstrom/drupalextension)
* [Mink](http://mink.behat.org/)
* [Selenium Server](http://docs.seleniumhq.org/)
* [Travis CI](https://travis-ci.org/)
* [Composer](http://getcomposer.org/)
* [Apache Ant](http://ant.apache.org/)

P.S. Do you really want to download and configure all this stuff yourself? Let
Drupal Distribution Manager do the dirty work for you!

### Attribution

Many of the concepts used in Drupal Distribution Manager were adopted from
[rb2k](https://github.com/rb2k)'s work on the
[Commons](https://drupal.org/project/commons) distribution.
