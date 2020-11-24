TAG=$1
README_FILE=./README.md
cp internal/README.template.md $README_FILE

if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -i '' -e "s/\${tag}/$TAG/" $README_FILE
else
  sed -i -e "s/\${tag}/$TAG/" $README_FILE
fi
