# Parity Notes

## Scope

This repository includes a parity check against the provided legacy controller logic and a documented attempt to validate against the currently live BimmerTech page/API.

## Live-Site Validation Status

The current public page is still available at:

- https://www.bimmer-tech.net/carplay/software-download

Direct scripted requests from this environment to the live API endpoint:

- `https://www.bimmer-tech.net/api2/carplay/software/version`

were blocked with HTTP `406 Not Acceptable` responses from ModSecurity, even when retried with browser-like form headers and origin/referer headers.

Because of that, full automated parity validation against the live API could not be completed from this environment.

## Local Parity Baseline

A parity harness is included at:

- [bin/parity_check.php](/Users/gwm-pc-03/Downloads/firmware-download-task/bin/parity_check.php)

It compares the current Symfony API output against an in-repo emulation of the provided legacy controller logic using representative cases:

- standard ST update
- standard ST latest
- standard GD update
- LCI CIC update
- LCI PRO EVO latest
- missing version
- missing hardware version
- invalid hardware version
- unknown software version

Run it from the repo root:

```bash
php bin/parity_check.php
```

## Confirmed Findings

- The live page structure still matches the original firmware lookup flow.
- Automated direct API parity against the live endpoint is blocked by ModSecurity from this environment.
- The included parity harness provides a regression baseline against the provided legacy controller logic.

## Known Differences

No confirmed response differences have been found yet between the current Symfony implementation and the provided legacy controller logic for the included parity cases.

Known limitation:

- live-site parity is not fully proven end-to-end because the live API rejected scripted requests with HTTP 406 from this environment.
