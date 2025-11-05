pipeline {
  agent any

  environment {
    // Change these if you like
    APP_NAME = 'expense-tracker'
    DOCKERHUB_CRED = 'dockerhub'       // Jenkins credential ID (optional)
    DOCKERHUB_USER = 'muhammadsajid44' // your Docker Hub user
    IMAGE_NAME = "${DOCKERHUB_USER}/${APP_NAME}-web"
    IMAGE_TAG  = "${env.BRANCH_NAME ?: 'main'}-${env.BUILD_NUMBER}"
    PUSH_IMAGE = 'false' // set true in job params or UI when you want to push
  }

  options {
    timestamps()
    ansiColor('xterm')
  }

  stages {
    stage('Checkout') {
      steps {
        git url: 'https://github.com/MSajid44/Expense-Tracker.git', branch: 'main'
      }
    }

    stage('PHP Lint') {
      steps {
        // Lint all PHP using a container so we don't need php-cli installed on the agent
        sh '''
          set -e
          docker run --rm -v "$PWD:/app" -w /app php:8.2-cli bash -lc '
            find web -type f -name "*.php" -print0 | xargs -0 -n1 php -l
          '
        '''
      }
    }

    stage('Build Web Image') {
      steps {
        sh '''
          set -e
          # Build the web image using web/Dockerfile as context root of the repo
          docker build -f web/Dockerfile -t "${IMAGE_NAME}:${IMAGE_TAG}" .
          docker tag "${IMAGE_NAME}:${IMAGE_TAG}" "${IMAGE_NAME}:latest"
        '''
      }
    }

    stage('Smoke Test with Docker Compose') {
      steps {
        sh '''
          set -eux
          # Bring up the stack for a quick check
          docker compose down || true
          docker compose up -d

          # Wait a bit for MySQL to initialize and app to boot
          sleep 15

          # Try curling the web container by name through its exposed port if mapped
          # If your compose maps web to localhost:8080, curl that. Otherwise curl the container directly.
          # The provided compose may expose port 80->8080; adjust if needed.
          # Try localhost first:
          if curl -sSf http://localhost:8080 >/dev/null 2>&1; then
            echo "HTTP OK on localhost:8080"
          else
            echo "localhost:8080 not reachable, trying container-to-container..."
            # Curl inside the web container targeting its own Apache
            WEB_CID=$(docker ps --format "{{.ID}} {{.Names}}" | awk "/web|sitev3-web|login-web/{print \$1; exit}")
            if [ -n "$WEB_CID" ]; then
              docker exec "$WEB_CID" bash -lc 'curl -sSf http://127.0.0.1/ > /dev/null'
            else
              echo "Web container not found"; exit 1
            fi
          fi
        '''
      }
      post {
        always {
          sh 'docker compose logs || true'
          sh 'docker compose down || true'
        }
      }
    }

    stage('Push Image (optional)') {
      when { expression { env.PUSH_IMAGE.toBoolean() } }
      steps {
        withCredentials([usernamePassword(credentialsId: "${DOCKERHUB_CRED}", passwordVariable: 'DOCKER_PWD', usernameVariable: 'DOCKER_USER')]) {
          sh '''
            set -e
            echo "$DOCKER_PWD" | docker login -u "$DOCKER_USER" --password-stdin
            docker push "${IMAGE_NAME}:${IMAGE_TAG}"
            docker push "${IMAGE_NAME}:latest"
            docker logout || true
          '''
        }
      }
    }
  }

  post {
    success {
      echo "Build OK. Image: ${IMAGE_NAME}:${IMAGE_TAG}"
    }
    failure {
      echo "Build failed. Check the logs above."
    }
  }
}
