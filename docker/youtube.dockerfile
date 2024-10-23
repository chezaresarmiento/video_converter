# Example Dockerfile

# Use an official Python image as the base image
FROM python:3.9-slim

# Install dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install yt-dlp (or you can use youtube-dl if you prefer)
RUN pip install yt-dlp

# Set the working directory
WORKDIR /app

# Copy your script or command into the container
COPY ./your_script.sh /app/

# Make your script executable
RUN chmod +x /app/your_script.sh

# Set the entry point to your script
ENTRYPOINT ["/app/your_script.sh"]
