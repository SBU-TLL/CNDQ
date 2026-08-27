#!/bin/bash
# Run this script from an empty CNDQ_test directory on the production server.
# Creates main/ (the git repo) and instance1–instance4, each with isolated data/.
#
# Usage:
#   cd /home/pstdenis/www/htdocs/CNDQ_test
#   bash setup-instances.sh

set -e

REPO="https://github.com/TLTMedia/CNDQ.git"
BRANCH="CNDQ_instances"
N=4

echo "=== Cloning $BRANCH branch into main/ ==="
git clone --branch "$BRANCH" "$REPO" main

echo ""
echo "=== Installing PHP dependencies ==="
(cd main/static && composer install --no-dev --no-interaction)

echo ""
echo "=== Creating main/data/ ==="
mkdir main/data

echo ""
echo "=== Building instance template (mainLink/) ==="
mkdir mainLink
ln -s ../main/static mainLink/static

echo ""
echo "=== Creating instance1 through instance$N ==="
for i in $(seq 1 $N); do
    cp -rP mainLink "instance$i"
    mkdir "instance$i/data"
    echo "  instance$i/ ready"
done

echo ""
echo "=== Setup complete ==="
echo ""
echo "Directory layout:"
ls -la
echo ""
echo "Next steps:"
echo "  1. Copy .env into main/static/.env  (DB credentials, admin password, etc.)"
echo "  2. Run the DB init for each instance:"
echo "       curl https://apps.tlt.stonybrook.edu/CNDQ_test/instance1/static/api/admin/init-database.php"
echo "       ... (repeat for instance2–instance$N)"
echo ""
echo "Admin panel URLs:"
BASE="https://apps.tlt.stonybrook.edu/CNDQ_test"
for i in $(seq 1 $N); do
    echo "  Instance $i: $BASE/instance$i/static/admin/"
done
