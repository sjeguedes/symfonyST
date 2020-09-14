[![Maintainability](https://api.codeclimate.com/v1/badges/827a5e2ff0a280908699/maintainability)](https://codeclimate.com/github/sjeguedes/symfonyST/maintainability)

# symfonyST

## Website project with Symfony 4 and Uikit frameworks
> https://symfony.com/doc/current/index.html  
> https://getuikit.com/docs/introduction

### Local installation

##### 1. Clone project repository  master branch on GitHub with:
```
$ git clone https://github.com/sjeguedes/symfonyST.git
```

##### 2. Configure particular project needed data and your own database parameters with environment variables in `env.local` file using `.env` provided example file:
```
# Website name displayed and visible in application:
$ WEBSITE_NAME=Your website name
# Default logo .jpeg file is provided in public/assets/images/mailing/ for application automatic emails:
$ ABSOLUTE_URL_FOR_HOSTED_LOGO=your_absolute_defined_path_to_hosted_mailing_logo
# Swift mailer configuration:
$ MAILER_DEV_URL=mailer_server_dev_parameters 
$ MAILER_URL=mailer_server_prod_parameters
# Sender email address:
$ MAILER_SENDER_EMAIL=your_mailing_sender_email_address
# Sender email address:
$ MAILER_SENDER_NAME=${WEBSITE_NAME} - Your mailing sender name
```
###### *This is important for local installation only:*  
###### *Please note to host provided logo in `public/assets/images/mailing/` which is used to be shown with absolute URL in automatic sent emails!*

##### 3. Adapt Doctrine "dbal" section configuration (driver and server_version) to your system requirements in `doctrine.yaml` file

##### 4. Encode plain passwords manually with command line interface (CLI) using defined Symfony password encoder to customise default users application hashed passwords. Then use these data to replace existing values in `src/Utils/Database/DataFixtures/yaml/user_fixtures.yaml` to insert them in database thanks to data fixtures. 

```
$ php bin/console security:encode-password Your_secured_password@1
```

###### *Please note your password must contain between 8 and 20 characters (digits and letters), with at least 1 digit, 1 uppercase letter, 1 lowercase letter and 1 special one.*

##### 5. Install dependencies defined in composer.json:

```
$ composer install
```

##### 6. Create database and schema with Doctrine migrations located in `src/Utils/Database/Migrations`:

```
$ php bin/console doctrine:database:create
$ php bin/console doctrine:migrations:migrate
```

##### 7. Add starting set of data with Doctrine fixtures located in `src/Utils/Database/DataFixtures`:

```
$ php bin/console doctrine:fixtures:load
```
###### *Please note a starting set of images files which corresponds to data fixtures is already present in project!*

##### 8. Compile assets (SASS and JS files), if you really need to, thanks to Webpack and npm configuration and files sources located in `public/assets/src`. You will probably have to update defined packages.

