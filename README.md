# Drupal Distribution Manager

This project is a command line tool that creates and manages Drupal
distributions with a focus on continuous integration. Distros are pre-configured
to integrate seamlessly with [Travis CI](https://travis-ci.org/) and are bundled
with [Apache Ant](http://ant.apache.org/) targets that make it easy to build up,
tear down, and run tests against your application.

## Installation

```
curl -sLO https://github.com/cpliakas/drupal-distro/releases/download/0.1.1/drupal-distro.phar
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

#### Commit Distro To Git Repository

```
cd distro_test
git init
git add -A
git remote add origin http://example.com/repo.git
git push -u origin master
```

## Usage For Distro Developers

Clone the repository and change into the resulting directory.

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
