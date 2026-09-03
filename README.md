# Endereco Shopware 6 Client

Endereco's Address Management Service plugin for Shopware 6, providing address validation,
autocomplete, email verification, and more for improved customer data quality.

## Requirements

- Shopware 6.6.0.0 or higher
- PHP 8.2, 8.3 or 8.4
- Composer (for development)
- Node.js and npm (for development)


## Development Setup

### Prerequisites

- PHP 8.2, 8.3 or 8.4 with the following extensions:
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

**Description:** Pulls Dockware Docker images for various Shopware versions and extracts the shop files into the `shops/` directory. The specific versions are defined in the script itself.

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
Prepares the plugin for distribution.

**Description:** Creates two distribution zip files:
- `EnderecoShopware6ClientStore-rc.zip` - Store version with renamed identifiers for Shopware Store
- `EnderecoShopware6Client-rc.zip` - GitHub version with original naming

Both versions have development files removed (vendor, node_modules, shell scripts, config files) and JavaScript comments stripped from Twig templates.

**Usage:**
```bash
./build.sh
```

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

**Description:** Checks all PHP files in the project (excluding vendor, node_modules, and shops) against PHP 8.2 and 8.3 using Docker images to ensure compatibility.

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
