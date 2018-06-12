<?php
namespace Deployer;

require 'recipe/symfony.php';

// Configurations
set('application', 'instance-terminator');
set('repository', 'git@github.com:tommynovember7/instance-terminator.git');
set('ssh_type', 'native');
set('ssh_multiplexing', true);
set('bin_dir', 'bin');
set('var_dir', 'var');

set('allow_anonymous_stats', false);
set('default_stage', 'dev');
set('dump_assets', true);
set('keep_releases', 10);
set('use_relative_symlink', true);

add('shared_dirs', ['var/logs']);
add('shared_files', ['app/config/parameters.yml']);
add('writable_dirs', ['app/cache']);

// Hosts
host('stg-p2batch-1')
    ->stage('dev')
    ->configFile('~/.ssh/config')
    ->set('deploy_path', '/var/application/instance-terminator');
host('stg-p2batch-2')
    ->stage('dev')
    ->configFile('~/.ssh/config')
    ->set('deploy_path', '/var/application/instance-terminator');

// tasks
task(
    'deploy',
    [
        'deploy:prepare',
        'deploy:lock',
        'deploy:release',
        'deploy:update_code',
        'deploy:vendors',
        'deploy:clear_paths',
        'deploy:create_cache_dir',
        'deploy:shared',
        'deploy:assets',
        'deploy:assets:install',
        'deploy:cache:warmup',
        'deploy:writable',
        'deploy:symlink',
        'deploy:unlock',
        'cleanup',
    ]
)->desc('push a new version');
