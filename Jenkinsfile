pipeline {
    agent any   // Means: run this job on any available Jenkins machine (agent)

    stages {    // Stages = steps or phases of your pipeline
        stage('Checkout Code') {
            steps {
                // This downloads (clones) your GitHub repository into Jenkins workspace
                git branch: 'main', url: 'https://github.com/MSajid44/Expense-Tracker.git'
            }
        }

        stage('Check PHP Syntax') {
            steps {
                // This checks all PHP files in your repo for syntax errors
                sh 'find . -name "*.php" -exec php -l {} \\;'
            }
        }

        stage('Build Docker Image') {
            steps {
                // This builds a Docker image using your Dockerfile (must be present in repo)
                sh 'docker build -t expense-tracker:latest .'
            }
        }
    }

    post {
        success {
            echo '✅ Build completed successfully!'
        }
        failure {
            echo '❌ Build failed. Check logs for details.'
        }
    }
}
