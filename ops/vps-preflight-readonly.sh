#!/usr/bin/env bash
# Run over an explicitly selected SSH target before preparing Samy staging.
# Read-only: no reloads, installations, containers, firewall, DNS or DB commands.
set -uo pipefail
printf 'SAMY_VPS_READONLY_PREFLIGHT_V1\n'
printf '\n[identity]\n'
id
hostname
uname -m
if [ -r /etc/os-release ]; then
  awk -F= '/^(NAME|VERSION_ID)=/ {print}' /etc/os-release
fi
printf '\n[capacity]\n'
df -h / /tmp
if command -v free >/dev/null 2>&1; then free -m; fi
printf '\n[runtimes]\n'
for tool in docker podman php nginx apache2 caddy node composer openssl tar python3; do
  command -v "$tool" || true
done
printf '\n[listening-ports]\n'
if command -v ss >/dev/null 2>&1; then ss -lnt; fi
printf '\n[container-inventory]\n'
if command -v docker >/dev/null 2>&1; then
  docker version --format 'server={{.Server.Version}}' 2>/dev/null || true
  docker compose version 2>/dev/null || true
  docker ps --format 'name={{.Names}} image={{.Image}} ports={{.Ports}} project={{.Label "com.docker.compose.project"}}' 2>/dev/null || true
  docker network ls --format '{{.Name}} {{.Driver}}' 2>/dev/null || true
fi
printf '\n[active-services]\n'
if command -v systemctl >/dev/null 2>&1; then
  systemctl list-units --type=service --state=running --no-pager --plain 2>/dev/null || true
fi
printf '\n[deployment-directory-names-only]\n'
for dir in /opt /srv /var/www; do
  if [ -d "$dir" ]; then find "$dir" -mindepth 1 -maxdepth 1 -type d -printf '%p\n' 2>/dev/null; fi
done
printf '\n[complete-no-mutations]\n'