# Endereco Shopware 6 Client

Endereco's Address Management Service plugin for Shopware 6, providing address validation,
autocomplete, email verification, and more for improved customer data quality.

## Requirements

- Shopware 6.7.0.1 or higher
- PHP 8.2 or higher (PHP 8.4 supported)
- Composer (for development)
- Node.js and npm (for development)


## Development Setup

### Prerequisites

- PHP 8.2 or higher with the following extensions:
  - curl
  - json
  - pdo_mysql
  - mbstring
  - xml
  - xmlwriter
  - tokenizer
  - ctype
  - fileinfo
- Composer
- Docker
- Node.js and npm
- Git


### Installation

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd endereco-shopware6-client
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies:**
   ```bash
   npm install
   ```

4. **Download Shopware versions for testing:**
   ```bash
   ./fetch_shops.sh
   ```

5. **Build frontend assets:**
   ```bash
   npm run build
   npm run build-styles
   ```

### Testing

Test the plugin with different Shopware 6 versions using:

```bash
./playground.sh
```

This will start a Dockware Container with your chosen Shopware version and automatically install the plugin.
The container will run on **port 80**, so please make sure that this port is not already in use on your system.

To test the plugin, log in to the Shopware backend at
http://localhost/admin  
Use Dockware’s default credentials:
```
User: admin
Password: shopware
```

In the Shopware backend, navigate to Extensions → My Extensions, open the configuration of the Endereco Plugin, and enter a valid API key.
You can request a test API key at [endereco.de](https://endereco.de).

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

#### Code Quality

Run quality checks before committing:

```bash
# Run all quality checks
composer qa

# Individual checks
composer phpcs    # Code style check
composer phpcbf   # Fix code style
composer phpstan  # Static analysis
composer phpmd    # Mess detection
```

## Scripts and Utilities

This repository includes several shell scripts and PHP utilities to assist with development and deployment. Below is a comprehensive list of each script with its description and usage instructions.

### Shell Scripts

#### `fetch_shops.sh`
Downloads and sets up different Shopware 6 versions for testing and development.

**Description:** Automatically downloads Shopware production releases (6.7.0.1, 6.7.1.2, 6.7.2.2, 6.7.3.1, 6.7.4.2) from GitHub and extracts them into the `shops/` directory.

**Usage:**
```bash
./fetch_shops.sh
```

#### `playground.sh`
Launches an interactive Shopware 6 development environment in Dockware.

**Description:** Starts a Docker container running a specified Shopware version with the plugin pre-installed. Allows selection of Shopware version and optional XDebug debugging. The container runs on port 80.

**Usage:**
```bash
./playground.sh
```

Then select your desired Shopware version and optionally enable XDebug. Access the shop at `http://localhost/admin` with credentials `admin` / `shopware`.

#### `build.sh`
Prepares the plugin for distribution in the Shopware Store.

**Description:** Creates a store-ready version of the plugin by copying source files, removing development dependencies (vendor, node_modules, development configs), and applying naming conventions to distinguish the store version. Strips out comments and development files.

**Usage:**
```bash
./build.sh
```

After execution, the `EnderecoShopware6ClientStore/` directory contains the store-ready plugin.

#### `build_admin.sh`
Builds the administration panel JavaScript assets using Docker.

**Description:** Uses a Dockware container to compile the administration panel's JavaScript components. Copies plugin files to the container, installs and activates the plugin, runs the build process, and copies the compiled assets back to the host.

**Usage:**
```bash
./build_admin.sh
```

Requires Docker to be running. The script automatically manages container lifecycle.

#### `test_php_versions.sh`
Tests PHP syntax compatibility across multiple PHP versions.

**Description:** Checks all PHP files in the project (excluding vendor, node_modules, and shops) against PHP 8.2, 8.3, and 8.4 using Docker images to ensure compatibility.

**Usage:**
```bash
./test_php_versions.sh
```

Requires Docker. Reports any syntax errors found during testing.

#### `check_phpmd.sh`
Detects unused code using PHP Mess Detector.

**Description:** Runs PHP Mess Detector (phpmd) on the `src/` and `tests/` directories to identify unused variables, parameters, and code.

**Usage:**
```bash
./check_phpmd.sh
```

Returns exit code 1 if issues are found, 0 otherwise.

#### `check_mysql_compatibility.sh`
Tests plugin compatibility with different MySQL versions.

**Description:** Sets up Docker containers for MySQL (versions 8.0-8.4) and Shopware to test plugin compatibility across MySQL versions. Creates isolated test environments with performance-tuned configurations.

**Usage:**
```bash
./check_mysql_compatibility.sh
```

Requires Docker. The script sets up and tears down containers automatically.


### PHP Scripts

#### `check_imports.php`
Validates that all use statements reference valid classes.

**Description:** Scans all PHP files in the `src/` directory, extracts use statements, and verifies that each imported class, interface, or trait actually exists. Reports missing classes to help identify import errors.

**Usage:**
```bash
php check_imports.php
```



## License

This project is licensed under the AGPLv3 License.