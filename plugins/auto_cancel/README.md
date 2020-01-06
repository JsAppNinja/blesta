# Auto Cancel

Automatically schedules suspended services for cancellation.

## Overview

This plugin adds an automated task to your Blesta installation that will
schedule suspended services for cancellation based on a couple settings:

- **Schedule Cancellation Days After Suspended**
    - This controls when a service receives a scheduled cancellation date.
- **Cancel Services Days After Suspended**
    - This controls the cancellation date a service receives.

For example, let's say a service is suspended on August 20. If
**Schedule Cancellation Days After Suspended** is set to **2 days**, on August
22 the service will receive a scheduled cancellation date. If
**Cancel Services Days After Suspended** is set to **4 days** the scheduled
cancellation date will then be set to August 24.

This allows you to control not only when a suspended service is canceled, but
when it receives its scheduled cancellation date.
