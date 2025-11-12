pipeline {
  agent any

  options {
    timestamps()
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

          RUN 'apt-get update -y'
          RUN 'apt-get install -y ca-certificates curl gnupg lsb-release zip unzip git'

          if ! command -v php >/dev/null 2>&1; then
            RUN 'apt-get install -y php-cli php-xml php-mbstring php-zip php-curl php-mysql'
          fi

          if ! command -v composer >/dev/null 2>&1; then
            curl -fsSL https://getcomposer.org/installer -o composer-setup.php
            HASH_EXPECTED=$(curl -fsSL https://composer.github.io/installer.sig)
            HASH_ACTUAL=$(php -r "echo hash_file(\"sha384\", \"composer-setup.php\");")
            [ "$HASH_EXPECTED" = "$HASH_ACTUAL" ] || { echo "Composer installer corrupt"; exit 1; }
            RUN 'php composer-setup.php --install-dir=/usr/local/bin --filename=composer'
            rm -f composer-setup.php
          fi

          if ! command -v docker >/dev/null 2>&1; then
            RUN 'install -m 0755 -d /etc/apt/keyrings'
            curl -fsSL https://download.docker.com/linux/$(. /etc/os-release; echo "$ID")/gpg | RUN 'gpg --dearmor -o /etc/apt/keyrings/docker.gpg'
            RUN 'chmod a+r /etc/apt/keyrings/docker.gpg'
            CODENAME="$(. /etc/os-release; echo "$VERSION_CODENAME")"
            RUN 'echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/$(. /etc/os-release; echo $ID) '$CODENAME' stable" > /etc/apt/sources.list.d/docker.list'
            RUN 'apt-get update -y'
            RUN 'apt-get install -y docker-ce docker-ce-cli containerd.io'
            if command -v systemctl >/dev/null 2>&1; then RUN 'systemctl enable --now docker'; fi
          fi

          if getent group docker >/dev/null 2>&1; then
            USER_NOW="$(id -un)"
            id -nG "$USER_NOW" | grep -qw docker || RUN "usermod -aG docker $USER_NOW" || true
          fi

          docker --version || true
          php -v || true
          composer -V || true
          git --version
          zip -v || true
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
          if [ ! -x vendor/bin/phpunit ] && command -v composer >/dev/null 2>&1 && [ -f composer.json ]; then
            composer require --dev phpunit/phpunit --with-all-dependencies || true
          fi

          if [ -x vendor/bin/phpunit ] || [ -f phpunit.xml ] || [ -f phpunit.xml.dist ]; then
            if [ -x vendor/bin/phpunit ]; then
              vendor/bin/phpunit --log-junit junit.xml || exit 1
            elif command -v phpunit >/dev/null 2>&1; then
              phpunit --log-junit junit.xml || exit 1
            else
              echo "WARNING: PHPUnit config found but phpunit not installed—skipping tests."
            fi
          else
            echo "No PHPUnit confi
