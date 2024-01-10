trigger:
- '*'

jobs:
- job: BuildAndTest
  pool:
    vmImage: 'aolb/php81-apache-oci:v1'

  steps:
  - script: |
      # Build backend
      echo "*** Install php packages..."
      composer install

      # Build frontend
      echo "*** Install frontend packages..."
      npm install
      gulp build

      # Validate PHP syntax
      echo "*** Validating PHP syntax..."
      find . -name '*.php' | grep -v ./vendor | xargs -I {} php -l -d display_errors=0 {}

      # Validate JS syntax
      echo "*** Validating JS syntax"
      jserrors=$(jshint --config ./application/configs/jshint.conf --verbose ./js --exclude js/w2ui-1.3.2.min.js,js/w2ui-1.3.2.js,js/modules/r.2.3.1.js,js/lib/highcharts/lib/svg2pdf.src.js,js/lib/highcharts/lib/svg2pdf.js,js/essm/bootstrap.js,js/lib/flexibility.js,js/modules/jquery.validate.js,js/modules/domReady.js,js/modules/backbone/lib/backbone-deep-model.js | grep -E E[0-9]+.$ | wc -l)
      if [ $jserrors -gt 0 ]; then
        exit 1
      fi

      # Validate CSS syntax (only the legacy non-sass-compiled CSS files)
      echo "*** Validating CSS syntax"
      csslint --errors=errors --quiet ./css/ --exclude-list=./css/reports/svr.css,./css/jqModal.css,./css/uniform.default.new.css,./css/uniform.rfq.css,./css/uniform.webreporter.css,./css/myshipserv.css,./css/search.css,./css/supplier/supplier.css,./css/compressed.css,./css/profile/company-blocked-list.css,./css/myshipserv_19_10_09.css,./css/essm/bootstrap.min.css,./css/essm/bootstrap.css
    displayName: 'Build and Test'

# Define the deployment jobs for UAT and Production
- job: DeployUAT
  dependsOn: BuildAndTest
  pool:
    vmImage: 'atlassian/default-image:2'
  condition: and(succeeded(), eq(variables['Build.SourceBranch'], 'refs/heads/main'))

  steps:
  - script: |
      echo "*** Build deployment container..."
      # TODO: Implement Docker login
      docker build -t pages -f Dockerfile.deploy-dev .

      echo "*** Push deployment container..."
      # TODO: Implement Docker push

      echo "*** Deploying..."
      # TODO: Initiate deployment on VPS
      echo "*** Done."
    displayName: 'Deploy to UAT'

- job: DeployProd
  dependsOn: BuildAndTest
  pool:
    vmImage: 'atlassian/default-image:2'
  condition: and(succeeded(), startsWith(variables['Build.SourceBranch'], 'refs/heads/deploy-prod'))

  steps:
  - script: |
      echo "*** Build deployment container..."
      # TODO: Implement Docker login
      docker build -t pages -f Dockerfile.deploy-prod .

      echo "*** Push deployment container..."
      # TODO: Implement Docker push

      echo "*** Deploying..."
      # TODO: Initiate deployment on VPS
      echo "*** Done."
    displayName: 'Deploy to Production'
