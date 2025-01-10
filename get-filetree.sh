#!/bin/bash

# Check if tree is installed
if ! command -v tree &> /dev/null; then
    echo "Tree command not found. Installing via Homebrew..."
    
    # Check if Homebrew is installed
    if ! command -v brew &> /dev/null; then
        echo "Homebrew not found. Please install Homebrew first:"
        echo '/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"'
        exit 1
    fi
    
    brew install tree
fi

# Generate the filetree with nice formatting
# -a shows hidden files
# -I '.git|node_modules' ignores git and node_modules directories
# --dirsfirst lists directories first
# -C enables colorization
# -F appends indicators to entries (/ for directories, * for executables, etc)

tree -a -I '.git|node_modules' --dirsfirst -C -F .
