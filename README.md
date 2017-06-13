# php-cronManager
php 定时任务管理器

# 配置
```php
//config.yml
manager-config:
  work_dir: 'php cron manager work dir, need the access permission to write',
  php_bin: 'php path, default is /usr/bin/php'
task-config:
  task-uuid:
    script: 'script-path',
    args: {_key: _value, ...},
    error_log: On/Off,
    log_file: ''
```

# 使用
```php
/usr/bin/php php-cronManager start/stop/restart/ps/kill [task-uuid]
```
