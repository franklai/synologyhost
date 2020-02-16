#!/usr/bin/env python3
import json
import logging
import re
import sys
from urllib.parse import urljoin

import requests

#  http://c20v1.lovetvshow.info/2020/01/cn200122-list.html
#  三生三世枕上書


def pretty_print(obj):
    print(json.dumps(obj, indent=2, ensure_ascii=False))


def get_all_episodes_by_url(url):
    # http://c20v1.lovetvshow.info/2020/01/cn200122-list.html
    resp = requests.get(url)
    html = resp.text

    pattern = r'<h3.*?><a href="([^"]+)".*?>(.+Ep[0-9]+)</a></h3>'
    matches = re.findall(pattern, html)

    episode_urls = []
    for item in matches:
        episode_url = item[0]
        if episode_url.find('http:') != 0:
            episode_url = urljoin(url, episode_url)
        episode_urls.append(episode_url)

    return episode_urls


def get_video_urls_by_url(url):
    # http://www.svlovetv.com/%e6%83%b3%e8%a6%8b%e4%bd%a0-%e7%ac%ac11%e9%9b%86-tw191117-ep11/
    resp = requests.get(url)
    html = resp.text

    pattern = r'<div id="video_ids" style="display:none;">(.+?)</div>'
    matches = re.findall(pattern, html)
    video_urls = [
        'http://www.dailymotion.com/video/{}'.format(x) for x in matches
    ]

    pattern = r'<div id="video_title" style="display:none;">(.+?)</div>'
    matched = re.search(pattern, html)
    if matched:
        logging.info('page title: {}'.format(matched.group(1)))
        logging.info('\turl: {}'.format(video_urls[0]))

    return video_urls


def main(url):
    logging.info('get all episodes from url: {}'.format(url))
    all_episodes_urls = get_all_episodes_by_url(url)

    logging.info('all episode count: {}'.format(len(all_episodes_urls)))
    urls = []
    for episode_url in all_episodes_urls:
        logging.info('get video urls from url: {}'.format(episode_url))
        video_urls = get_video_urls_by_url(episode_url)
        urls = video_urls + urls

    print("\n".join(urls))


def usage():
    print('Usage: {} [url]'.format(sys.argv[0]))


if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO)

    if len(sys.argv) < 2:
        usage()
        sys.exit(-1)

    url = sys.argv[1]
    main(url)
