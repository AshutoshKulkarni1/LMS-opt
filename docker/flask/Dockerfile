# ============================================================
# LMS / Test Platform - Flask + Gunicorn Dockerfile
# ============================================================
FROM python:3.11-slim

# Install curl for health check
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Create application directory and unprivileged user
WORKDIR /app

RUN useradd -m -u 1000 appuser

# Install Python dependencies
COPY src/python/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

# Copy Python analytics source code
COPY src/python /app

# Ensure correct file permissions
RUN chown -R appuser:appuser /app

USER appuser

# Internal Flask / Gunicorn port
EXPOSE 5000

# Health check against Flask /health endpoint
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://127.0.0.1:5000/health || exit 1

# Production WSGI server via Gunicorn
CMD ["gunicorn", "--bind", "0.0.0.0:5000", "--workers", "2", "--timeout", "120", "app:app"]
