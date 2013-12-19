api = 2
core = 7.x
includes[] = drupal-org-core.make

projects[solrtest][type] = "profile"
projects[solrtest][download][type] = "git"
projects[solrtest][download][url] = "{{ git.url }}"
projects[solrtest][download][branch] = "master"
