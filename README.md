# instance-terminator

A Symfony command to terminate its host.

The command could be run periodically as a cron job. 
First, it tries to find out a process which specified in config. 
Next, if succeeded, it will do nothing and silently exit, 
Or if failed, it tries to terminate the host after uploading specific logs to an S3 bucket. 


## Concept

This will help you who operate hundreds of EC2 instances. 
Monitoring task is tired and boring, so, I hope instances will terminate 
themselves after fulfilling their purposes.

This simply has two commands. One tries to find out a process 
to proceed with the termination sequences, the other tries upload specified 
logs to an S3 bucket. The process check command intended to find the process 
as the main purpose to run the host.

## Basic Usage
### Run Configurations
Revise `app/config/parameters.yml`. It has mailer, aws API connectivity, 
and target logs to upload to S3 bucket before terminating the host.

### Cron
Set `app:monitor` command as a cron job.
```apacheconfig
$ crontab -l
*/10 * * * * /var/www/project/current/bin/console app:monitor --env=prod >/dev/null 2>&1
```

## Developer Information

### Docker
This has docker configurations to run commands manually.
```apacheconfig
## boot a dummy metadata server
$ docker-compose up -d
```
```
## execute command
$ docker-compose run --rm php bin/console app:monitor
```

## Appendix

- [Symfony Documents](https://symfony.com/doc/3.4/reference/index.html)
