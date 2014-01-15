api = 2
core = 7.x
includes[] = drupal-org-core.make

projects[{{ profile }}][type] = "profile"
projects[{{ profile }}][download][type] = "git"
projects[{{ profile }}][download][url] = "{{ git.url }}"
projects[{{ profile }}][download][branch] = "master"
