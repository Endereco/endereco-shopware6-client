# Endereco Shopware 6 Client - Context for AI

## Project Identity
- **Type**: Shopware 6 Plugin
- **Version**: 6.6
- **Purpose**: Real-time address validation, correction, and management services

## Core Functionality
- Real-time address validation during customer input
- Automatic address correction with minor issues
- Address suggestions when corrections needed
- Street/house number splitting capability
- Autocomplete for postal codes, localities, streets
- Name validation/standardization with salutation setting
- Email address validation for deliverability
- Phone number validation with format standardization
- PayPal Express and Amazon Pay address validation
- Ultra-lightweight storefront API proxy for CORS-free validation

## Code Standards

### PHP
- **PSR-12** coding standard enforced
- **PSR-4** autoloading
- **PHPStan Level 8** (maximum strictness)
- **PHPMD** for mess detection
- **PHPUnit 9.5** for testing
- Always use strict types
- Service classes in `src/Service/`
- Subscribers in `src/Subscriber/`
- Entities in `src/Entity/`
- Migrations in `src/Migration/`

### JavaScript
- **ES6+** with Babel transpilation
- **ESLint** configured
- **Webpack** for bundling
- Entry point: `endereco.js`
- Uses `@endereco/js-sdk` v1.11.0
- Browser compatibility: >1%, last 2 versions

## Key Files and Their Purposes

### Main Plugin Class
- `src/EnderecoShopware6Client.php`: Extends `Shopware\Core\Framework\Plugin`

### Core Services
- `src/Service/EnderecoService.php`: Central coordination service
- `src/Service/AddressCheck/`: Address validation services
- `src/Service/AddressCorrection/`: Address correction logic
- `src/Service/AddressIntegrity/`: Data integrity services
- `src/Service/ApiConfiguration/`: API configuration management with caching

### Event Subscribers
- `src/Subscriber/CustomerAddressSubscriber.php`: Customer address lifecycle
- `src/Subscriber/OrderSubscriber.php`: Order processing
- `src/Subscriber/ConvertCartToOrderSubscriber.php`: Checkout integration
- `src/Subscriber/ConfigurationCacheInvalidationSubscriber.php`: Cache invalidation
- `src/Subscriber/ContextResolverListener.php`: Performance-optimized context resolution

### Configuration
- `src/Resources/config/config.xml`: Admin configuration schema
- `src/Resources/config/services.php`: Service container config
- `src/Resources/config/services/`: Modular service definitions
- `src/Resources/config/routes.php`: Storefront and API routes

### Frontend
- `endereco.js`: Main JavaScript entry
- `endereco.scss`: Main stylesheet
- `src/Resources/views/storefront/`: Twig template extensions

### Controllers
- `src/Controller/Storefront/AddressCheckProxyController.php`: Ultra-lightweight API proxy

### DTOs and Interfaces
- `src/DTO/ApiConfiguration.php`: API configuration data transfer object
- `src/Service/ApiConfiguration/ApiConfigurationFetcherInterface.php`: Configuration fetching contract
- `src/DependencyInjection/ReplaceContextResolverListenerPass.php`: Compiler pass for context optimization

## Database Extensions
- Customer address extensions with validation metadata
- Order address extensions
- Migration files follow naming: `Migration{timestamp}{Description}.php`
- Key fields: `amsStatus`, `amsPredictions`, `amsTimestamp`, `amsRequestPayload`

## Event Integration Points
- `CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT`
- `CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT`
- `framework.validation.address.*`
- `BuildValidationEvent`
- `SystemConfigChangedEvent`: For cache invalidation

## Quality Assurance Commands
- `composer qa`: Runs complete QA pipeline
- `composer cs`: PSR-12 code standards check
- `composer md`: PHPMD analysis
- `composer stan`: PHPStan Level 8 analysis
- `npm run build`: Build frontend assets

## Configuration System
- Plugin configuration via XML schema
- Per-sales-channel configuration support
- Feature toggles for individual services
- API key and URL configuration

## Development Constraints
- Separate branches per Shopware major version (6.4, 6.5, 6.6)
- PHP 7 compatibility for older versions
- Zero violations in all QA tools required
- Plugin must install/uninstall cleanly
- Cherry-pick changes between version branches

## File Naming Conventions
- Services: `{Purpose}Service.php`
- Subscribers: `{Context}Subscriber.php`
- Migrations: `Migration{Timestamp}{Description}.php`
- Templates: Follow Shopware directory structure

## API Integration
- RESTful Endereco API with configurable endpoints
- Session-based request tracking
- Error handling with fallback strategies
- Request header generation with agent information
- Ultra-lightweight storefront proxy controller at `/endereco/address-check`
- CSRF-free proxy endpoint for frontend integration
- Route `frontend.endereco.address.check` replaces legacy io.php proxy

## Testing Approach
- Multi-version testing across Shopware 6.6.0.0 to 6.6.10.4
- QA pipeline must pass before commits
- Installation testing for plugin lifecycle
- PHPStan configs for each Shopware version

## Build System
- Webpack for JavaScript bundling
- SCSS compilation
- Dual distribution: GitHub and Store versions
- Automated cleanup for production builds
- Legacy io.php file cleanup during installation/updates