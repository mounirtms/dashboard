#!/bin/bash
# Script to increase memory limits for technadminy7 user

# Increase PHP memory limit
echo "Increasing PHP memory limit..."
echo "memory_limit = 2048M" >> /opt/cpanel/ea-php82/root/etc/php.ini

# Set ulimit for current session
ulimit -m unlimited
ulimit -v unlimited
ulimit -n 65536

# Add limits to user profile
echo "Setting limits in user profile..."
echo "# Increase memory limits for Magento" >> /home/technadminy7/.bashrc
echo "ulimit -m unlimited" >> /home/technadminy7/.bashrc
echo "ulimit -v unlimited" >> /home/technadminy7/.bashrc
echo "ulimit -n 65536" >> /home/technadminy7/.bashrc

# Create a wrapper script for Magento commands with higher limits
echo "#!/bin/bash" > /home/technadminy7/magento-high-mem.sh
echo "ulimit -m unlimited" >> /home/technadminy7/magento-high-mem.sh
echo "ulimit -v unlimited" >> /home/technadminy7/magento-high-mem.sh
echo "ulimit -n 65536" >> /home/technadminy7/magento-high-mem.sh
echo 'php "$@"' >> /home/technadminy7/magento-high-mem.sh

chmod +x /home/technadminy7/magento-high-mem.sh

echo "Memory limits increased successfully!"
echo "To run Magento commands with higher memory limits, use:"
echo "/home/technadminy7/magento-high-mem.sh bin/magento <command>"