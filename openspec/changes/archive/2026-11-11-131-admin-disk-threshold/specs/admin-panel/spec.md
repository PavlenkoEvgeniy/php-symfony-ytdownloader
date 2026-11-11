# Spec Delta

## ADDED Requirements

### Requirement: Disk state on the Settings page

The Admin Panel Settings page SHALL show the current free space in the download
volume and whether it is below the effective minimum. It SHALL show the
effective minimum with its origin — the deployment default or an
administrator-set value — and accept the threshold as a non-negative whole
number of megabytes up to a supported maximum of 1048576, where an empty
value follows the deployment default.

#### Scenario: Effective minimum from the deployment default

- **WHEN** an administrator opens the Settings page before ever saving a threshold
- **THEN** the threshold field is empty
- **AND** the page shows the deployment default as the effective minimum

#### Scenario: Administrator sets the threshold

- **WHEN** an administrator saves a positive threshold in megabytes
- **THEN** the page shows that value as the administrator-set effective minimum

#### Scenario: Clearing the field returns to the deployment default

- **WHEN** an administrator clears the threshold field and saves
- **THEN** the page shows the deployment default as the effective minimum again

#### Scenario: Negative value is rejected

- **WHEN** an administrator tries to save a negative threshold
- **THEN** the value is not saved
- **AND** the page reports that the threshold must be zero or greater

#### Scenario: Value above the maximum is rejected

- **WHEN** an administrator tries to save a threshold above 1048576 megabytes
- **THEN** the value is not saved
- **AND** the page reports the supported maximum

#### Scenario: Free space below the effective minimum

- **WHEN** an administrator opens the Settings page while free space in the download volume is below the effective minimum
- **THEN** the page shows that the free space is below the minimum
