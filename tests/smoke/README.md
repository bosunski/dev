# Project smoke tests

Each directory under `projects/` is copied into a temporary workspace and exercised through the compiled `dev` binary. The fixtures use real package managers, services, runtime builds, and network requests on both Linux and macOS.

The sole simulated external command is Doppler. Its fixture verifies DEV's setup, download, and environment-file integration without requiring production credentials. All other fixtures execute their actual dependencies. The macOS job uses GitHub's Intel runner because its Apple-silicon runners do not expose the nested virtualization needed by Docker; Colima provides a real Docker daemon for the MySQL test.

Add coverage by creating another project directory containing a `dev.yml` and `smoke.sh`. The harness discovers it automatically.
