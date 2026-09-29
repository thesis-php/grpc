# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Client: a failed first connection attempt (e.g. an endpoint resolution error) is no longer
  cached forever — the next call retries it; `close()` no longer rethrows that failure.
