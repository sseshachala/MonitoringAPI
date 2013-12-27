#!/usr/bin/env python

"""
Various utils for global api

Example checks structure
{
  "name": "cpu.load",
  "params": [
    {
      "name": "warning",
      "description": "load per processor to measure average cpu load for 15
      minutes for warning to fire up",
      "required": "true",
      "ordering": "1",
      "type": "float"
    },
    {
      "name": "critical",
      "description": "load per processor to measure average cpu load for 15
      minutes for warning to fire up",
      "required": "true",
      "ordering": "1",
      "type": "float"
    },
  ],
  "inventory": "true",
  "configuration": [
    {
      "name": "cpuload_default_levels",
      "params": [
        {
          "name": "warning",
          "ref": "warning"
        },
        {
          "name": "critical",
          "ref": "critical"
        },
      ]
    }
  ]

}
"""


def data_structure():
    pass
