pipeline {
  agent any

  options {
    timestamps()
    ansiColor('xterm')
    buildDiscarder(logRotator(numToKeepStr: '20'))
  }

  parameters {
    string(name: 'GIT_BRANCH', defaultValue: 'main', description: 'Branch to build')
  }

  environment {
    REPO_URL     = 'https://github.com/MSajid44/Expense-Tracker.git'
    APP_NAME     = 'expense-tracker'
    IMAGE_TAG    = "${env.APP_NAME}:${env.BUILD_NUMBER}"
    IMAGE_TAR    = "${env.APP_NAME}-${env.BUILD_NUMBER}.tar"
    APP_ZIP      = "${env.APP_NAME}-${env.BUILD_NUMBER}.zip"
  }

  stages {
    stage('Checkout') {
      steps {
        deleteDir()
        git branch: "${params.GIT_BRANCH}", url: "${env.REPO_URL}"
        sh 'echo "Checked out $(git rev-parse --short HEAD) on branch ${GIT_BRANCH}"'
      }
    }

    stage('PHP Lint (syntax check)') {
      steps {
        sh '''
          set -euo pipefail
          if command -v php >/dev/null 2>&1; then
            echo "Running PHP lint..."
            # Lint all PHP files in parallel where possible
            if git ls-files '*.php' >/dev/null 2>&1; then
              git ls-files '*.php' | xargs -I{} -P 4 sh -c 'php -l "{}" >/dev/null || (echo "Syntax error in {}" && exit 1)'
              echo "PHP syntax OK."
            else
              echo "No PHP files found to lint."
            fi
          else
            echo "WARNING: php not found on agent—skipping lint."
          fi
        '''
      }
    }

    stage('Dependencies (Composer)') {
      steps {
        sh '''
          set -euo pipefail
          if [ -f composer.json ]; then
            if command -v composer >/dev/null 2>&1; then
              echo "Installing Composer dependencies..."
              composer install --no-interaction --prefer-dist
            else
              echo "WARNING: composer not found—skipping dependency install."
            fi
          else
            echo "composer.json not present—skipping."
          fi
        '''
      }
    }

    stage('Tests (PHPUnit if present)') {
      steps {
        sh '''
          set -euo pipefail
          if [ -x vendor/bin/phpunit ] || [ -f phpunit.xml ] || [ -f phpunit.xml.dist ]; then
            if [ -x vendor/bin/phpunit ]; then
              echo "Running PHPUnit..."
              vendor/bin/phpunit --log-junit junit.xml || exit 1
            elif command -v phpunit >/dev/null 2>&1; then
              echo "Running global phpunit..."
              phpunit --log-junit junit.xml || exit 1
            else
              echo "WARNING: PHPUnit config found but phpunit not installed—skipping tests."
            fi
          else
            echo "No PHPUnit config found—skipping tests."
          fi
        '''
      }
      post {
        always {
          junit allowEmptyResults: true, testResults: 'junit.xml'
        }
      }
    }

    stage('Build App Artifact (zip)') {
      steps {
        sh '''
          set -euo pipefail
          echo "Creating application zip (excluding VCS & build files)..."
          zip -r "${APP_ZIP}" . -x ".git/*" -x "node_modules/*" -x "*.tar" -x "*.zip"
          ls -lh "${APP_ZIP}"
        '''
      }
    }

    stage('Docker Build') {
      steps {
        sh '''
          set -euo pipefail
          if ! command -v docker >/dev/null 2>&1; then
            echo "ERROR: docker not found on agent. Please install Docker."
            exit 1
          fi

          if [ ! -f Dockerfile ]; then
            echo "No Dockerfile found—generating a safe default for PHP+Apache..."
            cat > Dockerfile <<'EOF'
            FROM php:8.2-apache
            RUN docker-php-ext-install pdo pdo_mysql mysqli && \
                a2enmod rewrite
            COPY . /var/www/html/
            WORKDIR /var/www/html
            # If you have composer.json, you can multi-stage build in your own Dockerfile later.
            EOF
          fi

          echo "Building image ${IMAGE_TAG}..."
          docker build -t "${IMAGE_TAG}" .
          echo "Built image:"
          docker images "${IMAGE_TAG}"
        '''
      }
    }

    stage('Export Docker Image to Workspace') {
      steps {
        sh '''
          set -euo pipefail
          echo "Saving Docker image to ${IMAGE_TAR}..."
          docker save -o "${IMAGE_TAR}" "${IMAGE_TAG}"
          ls -lh "${IMAGE_TAR}"
        '''
      }
    }

    stage('Archive Artifacts') {
      steps {
        archiveArtifacts artifacts: "${APP_ZIP}, ${IMAGE_TAR}", fingerprint: true
      }
    }
  }

  post {
    success {
      echo "Build complete. Artifacts archived: ${APP_ZIP}, ${IMAGE_TAR}"
    }
    always {
      script {
        echo "Workspace contents after build:"
        sh 'ls -lah'
      }
    }
  }
}
