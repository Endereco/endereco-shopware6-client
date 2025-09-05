# Customer Address Pipeline

The Customer Address Pipeline is a flexible, operation-based architecture for processing customer addresses in the Endereco Shopware 6 plugin. It ensures address data integrity, validation, and synchronization through a series of ordered operations.

## Architecture Overview

The pipeline follows a simple but powerful pattern:

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│  Workspace  │───▶│  Pipeline   │───▶│ Operations  │
└─────────────┘    └─────────────┘    └─────────────┘
      ▲                   │                   │
      │                   ▼                   ▼
      │            ┌─────────────┐    ┌─────────────┐
      └────────────│   Result    │◀───│  Process    │
                   └─────────────┘    └─────────────┘
```

### Core Components

- **`Pipeline`**: Orchestrates the execution of operations in priority order
- **`Workspace`**: Contains all data needed for address processing (address entity, extension, context, request)
- **`Operation`**: Individual processing units that handle specific aspects of address validation
- **`PipelineInterface`**: Contract for pipeline implementations

## Operation Flow

Operations execute in **priority order** (higher values = higher priority, 0 is higher than -10). The pipeline processes each operation through two phases:

1. **`applies(Workspace $workspace): bool`** - Determines if the operation should run
2. **`process(Workspace $workspace): void`** - Performs the actual work

### Skip Mechanism

Operations can call `$workspace->skipRemaining()` to halt further processing. This is useful for:
- Early termination when conditions aren't met
- Optimization when no further work is needed
- Error handling scenarios

## Operation Execution Order

```
Priority │ Operation              │ Purpose
─────────┼────────────────────────┼─────────────────────────────────────
   0     │ SetSkipFlag            │ Check if we can skip the rest of the pipeline
  -10    │ SetExtension           │ Ensure address extension exists in the entity
  -20    │ AssessValidationStatus │ Assess validation status and skip if current
  -30    │ ParseStreet            │ Split street into components
  -40    │ SetAmazonFlag          │ Set Amazon Pay flags
  -40    │ SetPayPalExpressFlag   │ Set PayPal Express flags
  -50    │ Validate               │ Perform address validation
```

### Execution Flow Diagram

```
┌─────────────────┐
│ Pipeline Start  │
└─────────┬───────┘
          ▼
┌─────────────────┐         Skip
│ SetSkipFlag     │──────────────┐
│ Priority: 0     │              │
└─────────┬───────┘              │
          ▼   Continue           │
┌─────────────────┐              │
│ SetExtension    │              │
│ Priority: -10   │              │
└─────────┬───────┘              │
          ▼                      │
┌────────────────────────┐  Skip │
│ AssessValidationStatus │───────┤
│ Priority: -20          │       │
└─────────┬──────────────┘       │
          ▼   Continue           │
┌─────────────────┐              │
│ ParseStreet     │              │
│ Priority: -30   │              │
└─────────┬───────┘              │
          ▼                      │
┌─────────────────┐              │
│ Flag Operations │              │
│ Priority: -40   │              │
└─────────┬───────┘              │
          ▼                      │
┌─────────────────┐              │
│ Validate        │              │
│ Priority: -50   │              │
└─────────┬───────┘              │
          ▼                      │
┌─────────────────┐ ◀────────────┘
│ Pipeline End    │
└─────────────────┘
```

## Detailed Operation Descriptions

### 1. SetSkipFlag (Priority: 0)
**Purpose**: Validates prerequisites and can skip the entire pipeline if conditions aren't met.

**Key Functions**:
- Checks if running in storefront context
- Verifies plugin is active for the sales channel
- Validates controller whitelist
- Sets skip flag if any prerequisite fails

**Skip Conditions**:
- Not running in storefront
- Plugin not active
- Controller not whitelisted
- Missing sales channel context

```php
// Whitelisted controllers for address processing
private array $whiteListedController = [
    'AddressController::accountEditAddress',
    'AddressController::addressBook', 
    'CheckoutController::confirmPage',
];
```

**Outcome**: Either allows pipeline to continue or skips all remaining operations.

---

### 2. SetExtension (Priority: -10)
**Purpose**: Ensures customer addresses have the required Endereco extension entity.

**Key Functions**:
- Checks if address extension already exists
- Creates extension with default values if missing
- Persists extension to database
- Links extension to address entity

**Default Extension Values**:
```php
[
    'addressId' => $addressEntity->getId(),
    'amsStatus' => AMS_STATUS_NOT_CHECKED,
    'amsPredictions' => []
]
```

**Outcome**: Every processed address will have an Endereco extension attached.

---

### 3. AssessValidationStatus (Priority: -20)
**Purpose**: Assesses current validation status and determines if further processing is needed.

**Key Functions**:
- Compares signature of the current address data with stored signature
- Resets validation metadata if address was modified
- Skips remaining operations if validation is current and complete

**Skip Logic**:
```php
if ($isRequestPayloadUpToDate && !$this->isValidationNeeded($addressExtension)) {
    $workspace->skipRemaining(); // No further processing needed
}
```

**Outcome**: Either resets outdated validation data or confirms data is current (skipping remaining operations).

---

### 4. ParseStreet (Priority: -30)
**Purpose**: Splits full street addresses into structured components (street name + building number).

**Key Functions**:
- Extracts country code and full street from address
- Uses Endereco's street splitting service
- Applies appropriate persistence strategy based on configuration
- Handles additional address lines if configured
- Ensures the street is split even if address validation is inactive (some customer need street and building number separately for third system integrations)

**Processing Flow**:
```php
$streetSplitResult = $this->streetSplitter->splitStreet(
    $fullStreet, 
    $additionalInfo, 
    $countryCode, 
    $context, 
    $salesChannelId
);

$addressPersistenceStrategy->execute(
    $streetSplitResult->getFullStreet(),
    $streetSplitResult->getAdditionalInfo(),
    $streetSplitResult->getStreetName(),
    $streetSplitResult->getBuildingNumber(),
    $addressDTO
);
```

**Outcome**: Address has properly separated street name and building number.

---

### 5. SetAmazonFlag (Priority: -40)
**Purpose**: Identifies and flags addresses created through Amazon Pay checkout.

**Key Functions**:
- Fetches customer entity associated with the address
- Checks for Amazon Pay account ID in customer custom fields
- Persists Amazon Pay flag to address extension
- Updates in-memory extension entity

**Detection Logic**:
```php
private function checkIfFromAmazon(CustomerEntity $customer): bool
{
    $customerCustomFields = $customer->getCustomFields();
    return isset($customerCustomFields['swag_amazon_pay_account_id']);
}
```

**Outcome**: Address extension has correct `isAmazonPayAddress` flag set. Can be used for prerequisite checking by validation operation.

---

### 6. SetPayPalExpressFlag (Priority: -40)
**Purpose**: Identifies and flags addresses created through PayPal Express checkout.

**Key Functions**:
- Fetches customer entity associated with the address
- Checks for PayPal Express payer ID in customer custom fields
- Persists PayPal flag to address extension
- Updates in-memory extension entity

**Detection Logic**:
```php
private function checkIfFromPayPal(CustomerEntity $customer): bool
{
    $customerCustomFields = $customer->getCustomFields();
    return isset($customerCustomFields['payPalExpressPayerId']);
}
```

**Outcome**: Address extension has correct `isPayPalAddress` flag set. Can be used for prerequisite checking by validation operation.

---

### 7. Validate (Priority: -50)
**Purpose**: Performs address validation using Endereco's validation service.

**Key Functions**:
- Checks if validation is needed (status is empty or `NOT_CHECKED`)
- Determines validation eligibility based on address source
- Performs up to 3 validation attempts with retry logic
- Applies validation results to address entity
- Registers session id's for accounting for billable validations

**Validation Scenarios**:
- **Existing Customer Check**: For customer-entered pre-existing addresses (if enabled)
- **PayPal Express Check**: For PayPal-sourced addresses (if enabled)

**Retry Logic**:

Some correction can be applied immediately, which means the address needs to be validated again after the application (because it changed).
```php
while ($attempts < MAX_VALIDATION_ATTEMPTS) {
    $addressCheckResult = $this->addressChecker->checkAddress(/*...*/);
    
    if ($addressCheckResult instanceof FailedAddressCheckResult) {
        return; // Graceful failure
    }
    
    $this->enderecoService->applyAddressCheckResult(/*...*/);
    
    if ($this->isPayloadStillValid()) {
        break; // Success
    }
    
    $attempts++;
}
```

**Accounting Logic**: Adds session IDs to billing storage for automatically selected addresses.

**Outcome**: Address has validated status codes and potentially corrected data.

## Usage Example

The pipeline is typically triggered when customer addresses are loaded:

```php
// In CustomerAddressSubscriber.php
foreach ($event->getEntities() as $entity) {
    if (!$entity instanceof CustomerAddressEntity) {
        continue;
    }

    $workspace = new Workspace();
    $workspace->setCustomerAddressEntity($entity);
    $workspace->setContext($context);
    
    if ($request !== null) {
        $workspace->setRequest($request);
    }

    $this->customerAddressPipeline->process($workspace);
}
```

## Configuration

Operations are automatically registered via dependency injection and collected using tagged services:

```php
// In service configuration
$services
    ->instanceof(Operation::class)
    ->tag('endereco.shopware6_client.customer_address_integrity_insurance');

$services->set(Pipeline::class)
    ->args([
        '$operations' => tagged_iterator(
            'endereco.shopware6_client.customer_address_integrity_insurance',
            null,
            null, 
            'getPriority'  // Sort by priority
        )
    ]);
```

## Error Handling

The pipeline implements several error handling strategies:

1. **Graceful Degradation**: Failed operations don't crash the pipeline
2. **Skip Mechanism**: Early termination when prerequisites aren't met
3. **Validation Retries**: Up to 3 attempts for address validation
4. **Runtime Exceptions**: For critical missing dependencies (e.g., missing extensions)

## Best Practices

### Creating New Operations

1. **Implement the `Operation` interface**
2. **Set appropriate priority** (consider execution dependencies)
3. **Check `$workspace->shouldSkip()`** in `applies()` method
4. **Validate required data** before processing
5. **Handle errors gracefully** without crashing the pipeline
6. **Document side effects** and data modifications

### Example Operation Template

```php
final class MyOperation implements Operation
{
    public static function getPriority(): int
    {
        return -25; // Choose based on dependencies
    }

    public function applies(Workspace $workspace): bool
    {
        if ($workspace->shouldSkip()) {
            return false;
        }

        // Add specific conditions
        return true;
    }

    public function process(Workspace $workspace): void
    {
        // Implement your logic
        // Consider calling $workspace->skipRemaining() if appropriate
    }
}
```