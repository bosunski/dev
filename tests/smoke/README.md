# Project smoke tests

Each directory under `projects/` is copied into a temporary workspace and exercised through the compiled `dev` binary. The fixtures use real package managers, services, runtime builds, and network requests on both Linux and macOS.

The sole simulated external command is Doppler. Its fixture verifies DEV's setup, download, and environment-file integration without requiring production credentials. All other fixtures execute their actual dependencies. GitHub's macOS runner starts Colima to provide the Docker daemon required by the real MySQL container test.

Add coverage by creating another project directory containing a `dev.yml` and `smoke.sh`. The harness discovers it automatically.
