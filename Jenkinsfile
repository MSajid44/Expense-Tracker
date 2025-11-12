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
    DEBIAN_FRONTEND = 'noninteractive'
  }

  stages {
    stage('Bootstrap Tools (PHP, Composer, Docker, git, zip)') {
      steps {
        sh '''
          set -euxo pipefail
          SUDO="$(command -v sudo || true)"
          RUN(){ if [ -n "$SUDO" ]; then sudo bash -lc "$*"; else bash -lc "$*"; fi }

          # Basic packages
          RUN 'apt-get update -y'
          RUN 'apt-get install -y ca-certificates curl gnupg lsb-release zip unzip git'

          # --- PHP CLI & extensions for typical Laravel/vanilla PHP needs ---
          if ! command -v php >/dev/null 2>&1; then
            RUN 'apt-get install -y php-cli php-xml php-mbstring php-zip php-curl php-mysql'
          fi

          # --- Composer ---
          if ! command -v composer >/dev/null 2>&1; then
            echo "Installing Composer..."
            curl -fsSL https://getcomposer.org/installer -o composer-setup.php
            HASH_EXPECTED=$(curl -fsSL https://composer.github.io/installer.sig)
            HASH_ACTUAL=$(php -r "echo hash_file(\"sha384\", \"composer-setup.php\");")
            if [ "$HASH_EXPECTED" != "$HASH_ACTUAL" ]; then
              echo "Composer installer corrupt"; exit 1
            fi
            RUN 'php composer-setup.php --install-dir=/usr/local/bin --filename=composer'
            rm -f composer-setup.php
          fi

          # --- Docker Engine (if missing) ---
          if ! command -v docker >/dev/null 2>&1; then
            echo "Installing Docker Engine..."
            RUN 'install -m 0755 -d /etc/apt/keyrings'
            curl -fsSL https://download.docker.com/linux/$(. /etc/os-release; echo "$ID")/gpg | RUN 'gpg --dearmor -o /etc/apt/keyrings/docker.gpg'
            RUN 'chmod a+r /etc/apt/keyrings/docker.gpg'
            CODENAME="$(. /etc/os-release; echo "$VERSION_CODENAME")"
            RUN 'echo \
              "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
              https://download.docker.com/linux/$(. /etc/os-release; echo $ID) \
              '$CODENAME' stable" \
              > /etc/apt/sources.list.d/docker.list'
            RUN 'apt-get update -y'
            RUN 'apt-get install -y docker-ce docker-ce-cli containerd.io'
            # Start docker if systemd available
            if command -v systemctl >/dev/null 2>&1; then
              RUN 'systemctl enable --now docker'
            else
              echo "NOTE: systemd not available; ensure Docker daemon is running."
            fi
          fi

          # Add current user to docker group (helps on future builds)
          if getent group docker >/dev/null 2>&1; then
            USER_NOW="$(id -un)"
            if ! id -nG "$USER_NOW" | grep -qw docker; then
              echo "Adding $USER_NOW to docker group (will apply on next login/session)..."
              RUN "usermod -aG docker $USER_NOW" || true
            fi
          fi

          docker --version || { echo "Docker still not usable. Will try using sudo for docker commands."; true; }
          php -v     || true
          composer -V || true
          git --version
          zip -v     || true
        '''
      }
    }

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
            FILES=$(git ls-files "*.php" || true)
            if [ -n "$FILES" ]; then
              echo "$FILES" | xargs -I{} -P 4 sh -c 'php -l "{}" >/dev/null || (echo "Syntax error in {}" && exit 1)'
              echo "PHP syntax OK."
            else
              echo "No PHP files found to lint."
            fi
          else
            echo "WARNING: php not found—skipping lint."
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
          # If phpunit not present but composer available, try to add it locally for this build
          if [ ! -x vendor/bin/phpunit ] && command -v composer >/dev/null 2>&1; then
            if [ -f composer.json ]; then
              echo "Ensuring phpunit is available (dev dependency)..."
              composer require --dev phpunit/phpunit --with-all-dependencies || true
            fi
          fi

          if [ -x vendor/bin/phpunit ] || [ -f phpunit.xml ] || [ -f phpunit.xml.dist ]; then
            if [ -x vendor/bin/phpunit ]; then
              echo "Running PHPUnit (vendor/bin/phpunit)..."
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
          DOCKER="docker"
          if ! command -v docker >/dev/null 2>&1; then
            echo "ERROR: docker CLI not found."; exit 1
          fi

          # If current user can't talk to daemon, try sudo docker
          if ! ${DOCKER} info >/dev/null 2>&1; then
            if command -v sudo >/dev/null 2>&1; then
              DOCKER="sudo docker"
            fi
          fi

          if [ ! -f Dockerfile ]; then
            echo "No Dockerfile found—generating a safe default for PHP+Apache..."
            cat > Dockerfile <<'EOF'
            FROM php:8.2-apache
            RUN docker-php-ext-install pdo pdo_mysql mysqli && \
                a2enmod rewrite
            COPY . /var/www/html/
            WORKDIR /var/www/html
            EOF
          fi

          echo "Building image ${IMAGE_TAG}..."
          ${DOCKER} build -t "${IMAGE_TAG}" .
          echo "Built image:"
          ${DOCKER} images "${IMAGE_TAG}"
        '''
      }
    }

    stage('Export Docker Image to Workspace') {
      steps {
        sh '''
          set -euo pipefail
          DOCKER="docker"
          if ! ${DOCKER} info >/dev/null 2>&1; then
            if command -v sudo >/dev/null 2>&1; then
              DOCKER="sudo docker"
            fi
          fi
          echo "Saving Docker image to ${IMAGE_TAR}..."
          ${DOCKER} save -o "${IMAGE_TAR}" "${IMAGE_TAG}"
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
