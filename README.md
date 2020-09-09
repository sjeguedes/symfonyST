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

##### 3. Adapt Doctrine "dbal" section configuration (driver and server_version) to your system requirements in `doctrine.yaml` file

##### 4. Encode plain passwords manually with command line interface (CLI) using defined Symfony password encoder to customise default users application hashed passwords. Then use these data to replace existing values in `src/Utils/Database/yaml/user_fixtures.yaml` to insert them in database thanks to data fixtures. 

```
$ php bin/console security:encode-password
```

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

##### 8. Compile assets (SASS and JS files), if you really need to, thanks to Webpack and npm configuration and files sources located in `public/assets/src`. Please note you will probably to update used packages.

