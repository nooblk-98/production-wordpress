# GitHub Actions Setup for Caddy Extended Docker Image

This workflow automatically builds and pushes the custom Caddy container image to Docker Hub with multi-architecture support.

## Setup

### 1. Prerequisites
- Docker Hub account at https://hub.docker.com
- GitHub repository with this workflow file

### 2. Create Docker Hub Access Token

1. Go to https://hub.docker.com/settings/security
2. Click "New Access Token"
3. Name it (e.g., `caddy-extended-builder`)
4. Select **Read, Write, Delete** permissions
5. Copy the token

### 3. Add GitHub Secrets

Go to your GitHub repository:
1. **Settings** → **Secrets and variables** → **Actions**
2. Click **New repository secret**
3. Add two secrets:

| Secret Name | Value |
|---|---|
| `DOCKER_HUB_USERNAME` | Your Docker Hub username |
| `DOCKER_HUB_TOKEN` | The access token from step 2 |

## Workflow Details

**Trigger Conditions:**
- On push to `main` or `master` branch (when Dockerfile/Caddyfile changes)
- Manual trigger via `workflow_dispatch`

**Platforms Supported:**
- `linux/amd64` - Intel/AMD 64-bit
- `linux/arm64` - ARM 64-bit (Apple Silicon, newer ARM servers)
- `linux/arm/v7` - 32-bit ARM (Raspberry Pi, older devices)

**Image Tags:**
- `nooblk98/caddy-extended:latest` - Always points to latest build
- `nooblk98/caddy-extended:main-<sha>` - Branch + commit SHA

## GitHub Actions Permissions

The workflow uses `GITHUB_TOKEN` for caching, which is automatically available.

## Usage

Once setup is complete, the image builds automatically on push. Monitor progress:

1. Go to **Actions** tab in GitHub
2. Select **Build & Push Caddy Extended Docker Image**
3. View real-time build logs

Pull the built image:
```bash
docker pull nooblk98/caddy-extended:latest
```

## Troubleshooting

### Build fails with "permission denied"
- Check `DOCKER_HUB_USERNAME` and `DOCKER_HUB_TOKEN` secrets are correct
- Ensure token has **Read, Write, Delete** permissions

### Multi-architecture build is slow
- First build takes longer (initializes buildx)
- Subsequent builds use GitHub Actions cache layer

### Image not available immediately
- Docker Hub may take 1-2 minutes to fully index the image
- Check Docker Hub dashboard for build status

## Disabling

To disable this workflow:
1. Go to **Actions** → **Build & Push Caddy Extended Docker Image**
2. Click **...** → **Disable workflow**

Or delete the file: `.github/workflows/build-caddy-extended.yml`
