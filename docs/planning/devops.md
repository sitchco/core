# DevOps

This document provides a reference for the DevOps components that support the Sitchco platform. It outlines strategies, implementations, and distribution processes that ensure consistent developer experience, automated builds, and reliable client handoffs. You’ll find guidance on pre-commit checks, TypeScript usage, package publishing, artifact generation, CI/CD infrastructure, and container management; along with current status and next steps for each.

### Husky Pre-Commit Strategy

* **Status:** Implemented (scoped)
* **Next Steps:** Further husky implementation per project as needs arise
* **Documentation:** [sitchco-packages/docs/husky-support.md](https://github.com/sitchco/sitchco-packages/blob/main/docs/husky-support.md)

Defines the approach for enforcing pre-commit checks (linting, formatting, etc.) using Husky. The documented strategy explains how Husky integrates with the existing tooling, but no hooks are currently enabled. Future developers can decide when and how to adopt this workflow.

This is listed as a scoped implementation as only the framework has been established. Future package developers will determine what hooks are necessary for their situation.

---

### TypeScript Support Strategy

* **Status:** Researched and Documented
* **Next Steps:** Refer to the documentation whenever considering TypeScript or starting a new project
* **Documentation:** [sitchco-packages/docs/typescript-guidelines.md](https://github.com/sitchco/sitchco-packages/blob/main/docs/typescript-guidelines.md)

Outlines the recommended use of TypeScript for platform packages as well as provides technical guidance on how to use it in the project. The guidance highlights when TypeScript provides value (e.g., shared libraries and reusable utilities) versus when plain JavaScript is sufficient (e.g., child themes). No migration has been performed; this is a forward-looking reference.

---

### Composer Distribution & Artifact Packaging

* **Status:** Implemented (scoped)
* **Next Steps:** Adopt the GitHub action for other plugins and themes
* **Documentation:** [sitchco-core/docs/artifact-generation.md](https://github.com/sitchco/core/blob/master/docs/artifact-generation.md)

Defines the process for distributing MU plugins, themes, and custom plugins as Composer-compatible packages. The workflow supports both development mode (`--prefer-src`, editable source) and production mode (`--prefer-dist`, precompiled distributions). Release artifacts are built through CI pipelines, tagged with versions, and include only production-ready and compiled /dist assets while excluding development files.

This is listed as a scoped implementation as only sitchco-core has been implemented. Parent and child themes can use the same GitHub action to release to Composer, but this will need to be done on a per-project basis.

---

### NPM Package Publishing

* **Status:** Implemented
* **Next Steps:** Ongoing maintenance only
* **Documentation:** [sitchco-packages/docs/release-process.md](https://github.com/sitchco/sitchco-packages/blob/main/docs/release-process.md)

Specifies the process for publishing shared packages (e.g., build tools, configs) to NPM. This allows consistent versioning, distribution, and reuse across repositories. The pipeline is in place and actively publishes updates as packages are released.

---

### GitHub Actions Container Image

* **Status:** Implemented (scoped)
* **Next Steps:** Set as the build image when needed
* **Documentation:** [sitchco-containers/docker/sitchco-build/Dockerfile](https://github.com/sitchco/sitchco-containers/blob/main/docker/sitchco-build/Dockerfile)

Defines a Docker image that can be used to build Sitchco projects through GitHub Actions. The image is available in DockerHub but not yet used as the default build image.

This is listed as a scoped implementation as there is not yet a need to run our GitHub actions through a custom container. Therefore, it has not yet been set as the build image in any capacity.

---

### Docker Container Registry & Management

* **Status:** Implemented (scoped)
* **Next Steps:** Migrate legacy containers into this repo or deprecate as appropriate
* **Documentation:** [Sitchco-Containers repository](https://github.com/sitchco/sitchco-containers)

Establishes a centralized Docker registry and release process for platform containers. New images are built and deployed through the registry; older containers remain in legacy repositories and require review. This provides a path forward for modernizing container usage across the environment, and supports automated deployment to DockerHub.
