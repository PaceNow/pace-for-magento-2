VERSION=$1
COMPOSER_JSON=./composer.json
cp internal/composer.json.template $COMPOSER_JSON

if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -i '' -e "s/\${version}/$VERSION/" $COMPOSER_JSON
else
  sed -i -e "s/\${version}/$VERSION/" $COMPOSER_JSON
fi
