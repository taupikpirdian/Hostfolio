#!/usr/bin/env sh
set -eu

COMPOSE_FILE="docker-compose.yml"

echo "🚀 Starting deployment..."

echo "🔍 Validating configuration..."
docker compose -f "$COMPOSE_FILE" config --quiet

echo "🔨 Building images using host network for the app build..."
docker compose -f "$COMPOSE_FILE" build

echo "▶️ Deploying containers..."
docker compose -f "$COMPOSE_FILE" up -d --no-build

echo "📋 Container status:"
docker compose -f "$COMPOSE_FILE" ps

echo "✅ Deployment complete!"
