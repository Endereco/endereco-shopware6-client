#!/bin/bash

# MySQL version to check
mysql_versions=("8.0" "8.1" "8.2" "8.3" "8.4")

# Shopware version for dockware image
shopware_version="6.7.0.1"

# Plugin variables
plugin_host_dir="./"  # Directory on host where your plugin files are stored
plugin_container_dir="/var/www/html/custom/plugins/EnderecoShopware6Client"  # Directory in the container where plugins are stored

base_container_name="check_mysql_compatibility"
network_name="${base_container_name}_network"

# Mysql variables
mysql_container_name="${base_container_name}_mysql"
mysql_user=shopware
mysql_password=shopware
mysql_database=shopware
mysql_environment_variables="
-e MYSQL_USER=${mysql_user}
-e MYSQL_PASSWORD=${mysql_password}
-e MYSQL_DATABASE=${mysql_database}
-e MYSQL_RANDOM_ROOT_PASSWORD=yes
"

# Dockware variables
dockware_container_name="${base_container_name}_dockware"
dockware_image="dockware/dev:$shopware_version"
dockware_environment_variables="
-e DATABASE_URL=mysql://${mysql_user}:${mysql_password}@${mysql_container_name}/${mysql_database}
"

# Setup containers, copy plugin into dockware container, expects mysql version as first and only argument
setup() {
  # Create network for communication between dockware and mysql container, no port mappings required
    docker network create $network_name

    # Create mysql container
    docker run \
        -d \
        $mysql_environment_variables \
        --name $mysql_container_name \
        --network=$network_name \
        --health-cmd="mysql -u $mysql_user -p$mysql_password -e 'SHOW TABLES;' $mysql_database > /dev/null || exit 1" \
        --health-interval=5s \
        --health-timeout=2s \
        --health-retries=10 \
        "mysql:$1"

    # Wait until mysql container is healthy, fail when mysql container becomes unhealthy
    until [ "$(docker inspect --format='{{json .State.Health.Status}}' "$mysql_container_name")" = "\"healthy\"" ]; do
      health_status="$(docker inspect --format='{{json .State.Health.Status}}' "$mysql_container_name")"

      if [ "$health_status" = "\"unhealthy\"" ]; then
          echo "MySQL container health check failed (unhealthy)."
          return 1
      fi

      echo "Waiting for MySQL container to become healthy..."
      sleep 5
    done

    # Create dockware container
    # Waiting for healthy not necessary, console is available right away
    docker run \
      -d \
      $dockware_environment_variables \
      --name $dockware_container_name \
      --network=$network_name \
      $dockware_image

    # Install shopware in dockware container to run migrations in mysql container
    # Needs --force to ignore the install.lock file
    docker exec $dockware_container_name php bin/console system:install --force

    # Copy plugin files to container and set permissions
    docker cp $plugin_host_dir $dockware_container_name:$plugin_container_dir
    docker exec -u root $dockware_container_name chown -R www-data:www-data $plugin_container_dir
}

# Checks mysql compatibility by installing the plugin
run_mysql_compatibility_check() {
  # Install and activate plugin via the console
  docker exec $dockware_container_name php bin/console plugin:refresh
  docker exec $dockware_container_name php bin/console plugin:install --activate EnderecoShopware6Client
}

# Stops and removes dockware and mysql container and the removes the network
tear_down() {
  docker stop $dockware_container_name
  docker container rm $dockware_container_name
  docker stop $mysql_container_name
  docker container rm $mysql_container_name
  docker network rm $network_name
}

for mysql_version in "${mysql_versions[@]}"; do
  # setup containers
  setup $mysql_version

  # Run compatibility check
  run_mysql_compatibility_check

  # Check for failure, if so tear_down and exit
  if [ $? -ne 0 ]; then
    echo "compatibility check failed for mysql version $mysql_version"
    tear_down
    exit 1
  fi

  # Tear down after each MySQL version so that no running containers remain and the next check is restarted from scratch.
  tear_down
done
