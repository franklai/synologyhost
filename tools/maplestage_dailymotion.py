#!/usr/bin/env python3
import json
import logging
import re
import sys

import requests


def pretty_print(obj):
    print(json.dumps(obj, indent=2, ensure_ascii=False))


def parse_page_data(line):
    pattern = r'pageData = ({.*});'
    matched = re.search(pattern, line)
    if matched:
        return json.loads(matched.group(1))
    return None


def parse_page_props(line):
    pattern = r'pageProps = ({.*});'
    matched = re.search(pattern, line)
    if matched:
        return json.loads(matched.group(1))
    return None


def get_video_urls(props):
    dailymotion = None
    for src in props['videoGroups']:
        if src['name'] == 'dailymotion':
            dailymotion = src
            break

    if dailymotion is None:
        return None

    urls = []
    for video in dailymotion['videos']:
        video_id = video['id']
        url = 'https://www.dailymotion.com/video/{}'.format(video_id)
        urls.append(url)

    return urls


def get_all_episodes(props):
    urls = []
    for item in props['allEpisodes']:
        url = 'http://maplestage.com{}'.format(item['href'])
        urls.append(url)
    return urls


def get_props_by_url(url):
    resp = requests.get(url)
    html = resp.text

    page_props = None
    for line in html.split('\n'):
        if line.find('var pageProps ') != -1:
            page_props = parse_page_props(line)
            break

    return page_props


def get_all_episodes_by_url(url):
    props = get_props_by_url(url)
    return get_all_episodes(props)


def get_video_urls_by_url(url):
    props = get_props_by_url(url)
    return get_video_urls(props)


def main(url):
    logging.info('get all episodes from url: {}'.format(url))
    all_episodes_urls = get_all_episodes_by_url(url)

    logging.info('all episode count: {}'.format(len(all_episodes_urls)))
    urls = []
    for episode_url in all_episodes_urls:
        logging.info('get video urls from url: {}'.format(episode_url))
        video_urls = get_video_urls_by_url(episode_url)
        urls += video_urls

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
