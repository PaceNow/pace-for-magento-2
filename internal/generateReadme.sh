TAG=$1
README_FILE=./README.md
cp internal/README.template.md $README_FILE

sed -i '' -e "s/\${tag}/$TAG/" $README_FILE
